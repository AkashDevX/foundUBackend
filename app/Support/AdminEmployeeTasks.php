<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeTaskAssignment;
use App\Models\EmployeeTaskCompletion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class AdminEmployeeTasks
{
    /**
     * Whether a task's optional role/shift filters match the employee context.
     */
    public static function matchesRoleAndShift(
        ?int $taskJobTitleId,
        ?int $taskShiftId,
        ?int $employeeJobTitleId,
        ?int $employeeShiftId,
    ): bool {
        if ($taskJobTitleId !== null && $taskJobTitleId !== $employeeJobTitleId) {
            return false;
        }

        if ($taskShiftId !== null && $taskShiftId !== $employeeShiftId) {
            return false;
        }

        return true;
    }

    /**
     * Employee task allocations (optionally filtered to a calendar date).
     *
     * @return Collection<int, EmployeeTaskAssignment>
     */
    public static function employeeAssignmentsFor(Employee $employee, ?string $date = null): Collection
    {
        $employee->loadMissing(['assignedJobTitle', 'assignedShift']);

        return EmployeeTaskAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->when(
                is_string($date) && $date !== '',
                static fn (Builder $query): Builder => $query->where(function (Builder $inner) use ($date): void {
                    $inner->whereNull('scheduled_date')->orWhere('scheduled_date', $date);
                })
            )
            ->with(['workLocation', 'jobTitle', 'shift'])
            ->orderByRaw('scheduled_date IS NULL DESC')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('title')
            ->get()
            ->filter(fn (EmployeeTaskAssignment $assignment): bool => self::matchesRoleAndShift(
                $assignment->job_title_id,
                $assignment->shift_id,
                $employee->job_title_id,
                $employee->shift_id,
            ))
            ->values();
    }

    /**
     * Calendar date used when recording or reading completion for a task.
     */
    public static function completionDateForAssignment(EmployeeTaskAssignment $assignment, string $viewDate): string
    {
        return $assignment->scheduled_date?->toDateString() ?? $viewDate;
    }

    /**
     * Resolve the completion row for a task on the selected calendar date.
     */
    public static function completionForAssignment(
        EmployeeTaskAssignment $assignment,
        Collection $completions,
        string $viewDate,
    ): ?EmployeeTaskCompletion {
        $completionDate = self::completionDateForAssignment($assignment, $viewDate);

        return $completions->first(
            static fn (EmployeeTaskCompletion $row): bool => (int) $row->employee_task_assignment_id === (int) $assignment->id
                && $row->completion_date->toDateString() === $completionDate,
        );
    }

    /**
     * Admin display — primary date bucket, then any completion for this assignment.
     */
    public static function completionForAssignmentDisplay(
        EmployeeTaskAssignment $assignment,
        Collection $completions,
        string $viewDate,
    ): ?EmployeeTaskCompletion {
        $primary = self::completionForAssignment($assignment, $completions, $viewDate);
        if ($primary !== null) {
            return $primary;
        }

        return $completions
            ->filter(static fn (EmployeeTaskCompletion $row): bool => (int) $row->employee_task_assignment_id === (int) $assignment->id)
            ->sortByDesc(static fn (EmployeeTaskCompletion $row) => $row->completed_at?->timestamp ?? 0)
            ->first();
    }

    /**
     * All active personal task allocations for the signed-in employee (mobile list).
     *
     * @return Collection<int, EmployeeTaskAssignment>
     */
    public static function activeAssignmentsFor(Employee $employee): Collection
    {
        $employee->loadMissing(['assignedJobTitle', 'assignedShift']);

        return EmployeeTaskAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->with(['workLocation', 'jobTitle', 'shift'])
            ->orderByRaw('scheduled_date IS NULL DESC')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('title')
            ->get()
            ->filter(fn (EmployeeTaskAssignment $assignment): bool => self::matchesRoleAndShift(
                $assignment->job_title_id,
                $assignment->shift_id,
                $employee->job_title_id,
                $employee->shift_id,
            ))
            ->values();
    }

    /**
     * Combined mobile payload for the signed-in employee.
     *
     * @return array<string, mixed>
     */
    public static function mobilePayloadForEmployee(Employee $employee, ?string $date = null): array
    {
        $dateString = self::resolveDate($date);
        $employee->loadMissing(['assignedJobTitle', 'assignedShift', 'workLocation']);

        $assignments = self::activeAssignmentsFor($employee);
        $completions = self::completionsForEmployee($employee);

        $tasks = $assignments
            ->map(function (EmployeeTaskAssignment $assignment) use ($completions, $dateString): array {
                $completion = self::completionForAssignment($assignment, $completions, $dateString);

                return self::assignmentToPayload($assignment, $completion);
            })
            ->all();

        $summary = self::completionSummary($tasks);

        return [
            'date' => $dateString,
            'work_location' => $employee->workLocation instanceof \App\Models\WorkLocation ? [
                'id' => $employee->workLocation->id,
                'name' => $employee->workLocation->name,
            ] : null,
            'tasks' => $tasks,
            'counts' => $summary,
        ];
    }

    /**
     * Mark a task complete or pending for the signed-in employee on a calendar date.
     *
     * @return array<string, mixed>
     */
    public static function setTaskCompletion(
        Employee $employee,
        int $taskId,
        string $date,
        bool $completed,
    ): array {
        $conn = $employee->getConnectionName();

        $assignment = self::activeAssignmentsFor($employee)->firstWhere('id', $taskId);
        if (! $assignment instanceof EmployeeTaskAssignment) {
            throw new InvalidArgumentException('This task is no longer available.');
        }

        $viewDate = self::resolveDate($date);
        $completionDate = self::completionDateForAssignment($assignment, $viewDate);

        if ($completed) {
            EmployeeTaskCompletion::on($conn)->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'employee_task_assignment_id' => $assignment->id,
                    'completion_date' => $completionDate,
                ],
                [
                    'completed_at' => DisplayTimezone::now(),
                ],
            );
        } else {
            EmployeeTaskCompletion::on($conn)
                ->where('employee_id', $employee->id)
                ->where('employee_task_assignment_id', $assignment->id)
                ->whereDate('completion_date', $completionDate)
                ->delete();
        }

        $completion = self::completionForAssignment(
            $assignment,
            self::completionsForEmployee($employee),
            $viewDate,
        );

        return self::assignmentToPayload($assignment, $completion);
    }

    /**
     * @return Collection<int, EmployeeTaskCompletion>
     */
    public static function completionsForEmployee(Employee $employee): Collection
    {
        return EmployeeTaskCompletion::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->get();
    }

    /**
     * @return Collection<int, EmployeeTaskCompletion>
     */
    public static function completionsForEmployeeOnDate(Employee $employee, string $date): Collection
    {
        return EmployeeTaskCompletion::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->whereDate('completion_date', $date)
            ->get()
            ->keyBy(static fn (EmployeeTaskCompletion $completion): int => (int) $completion->employee_task_assignment_id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function assignmentToPayload(
        EmployeeTaskAssignment $assignment,
        ?EmployeeTaskCompletion $completion = null,
    ): array {
        return [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => self::nullableTrimmed($assignment->description),
            'notes' => self::nullableTrimmed($assignment->notes),
            'work_location' => self::locationSummary($assignment->workLocation),
            'job_title' => self::jobTitleSummary($assignment->jobTitle),
            'shift' => self::shiftSummary($assignment->shift),
            'scheduled_date' => $assignment->scheduled_date?->toDateString(),
            'scheduled_date_display' => $assignment->scheduled_date !== null
                ? DisplayTimezone::formatDate($assignment->scheduled_date)
                : null,
            'start_time' => self::formatStoredTimeHm($assignment->start_time),
            'end_time' => self::formatStoredTimeHm($assignment->end_time),
            'time_range' => self::timeRangeLabel($assignment->start_time, $assignment->end_time),
            'completed' => $completion !== null,
            'completed_at' => $completion?->completed_at?->toIso8601String(),
            'completed_at_display' => $completion?->completed_at !== null
                ? DisplayTimezone::formatDateTime($completion->completed_at)
                : null,
            'completion_date' => $completion?->completion_date?->toDateString(),
            'completion_date_display' => $completion?->completion_date !== null
                ? DisplayTimezone::formatDate($completion->completion_date)
                : null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return array{total: int, completed: int, pending: int}
     */
    public static function completionSummary(array $tasks): array
    {
        $total = count($tasks);
        $completed = count(array_filter($tasks, static fn (array $task): bool => ($task['completed'] ?? false) === true));

        return [
            'total' => $total,
            'completed' => $completed,
            'pending' => max(0, $total - $completed),
        ];
    }

    public static function resolveDate(?string $date): string
    {
        if (is_string($date) && $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        return DisplayTimezone::now()->toDateString();
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function locationSummary(?\App\Models\WorkLocation $location): ?array
    {
        if ($location === null) {
            return null;
        }

        return [
            'id' => $location->id,
            'name' => $location->name,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private static function jobTitleSummary(?\App\Models\JobTitle $jobTitle): ?array
    {
        if ($jobTitle === null) {
            return null;
        }

        return [
            'id' => $jobTitle->id,
            'name' => $jobTitle->name,
        ];
    }

    /**
     * @return array{id: int, name: string, start_time: string|null, end_time: string|null}|null
     */
    private static function shiftSummary(?\App\Models\Shift $shift): ?array
    {
        if ($shift === null) {
            return null;
        }

        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'start_time' => self::formatStoredTimeHm($shift->start_time),
            'end_time' => self::formatStoredTimeHm($shift->end_time),
        ];
    }

    private static function nullableTrimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private static function formatStoredTimeHm(mixed $value): ?string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return null;
    }

    private static function timeRangeLabel(mixed $start, mixed $end): ?string
    {
        $startHm = self::formatStoredTimeHm($start);
        $endHm = self::formatStoredTimeHm($end);

        if ($startHm === null && $endHm === null) {
            return null;
        }

        if ($startHm !== null && $endHm !== null) {
            return $startHm.' – '.$endHm;
        }

        return $startHm ?? $endHm;
    }
}
