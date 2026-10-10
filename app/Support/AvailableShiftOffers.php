<?php

namespace App\Support;

use App\Models\AvailableShiftRequest;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AvailableShiftOffers
{
    public static function assignmentNote(?string $employeeName, bool $assignedToViewer = false): string
    {
        if ($assignedToViewer) {
            return 'This shift was available and has been assigned to you.';
        }

        $name = trim((string) $employeeName);

        return 'This shift was available and has been assigned to '.($name !== '' ? $name : 'another employee').'.';
    }

    /**
     * Pending claims shown on the weekly schedule available-shift cards.
     *
     * @return list<array<string, mixed>>
     */
    public static function pendingRequestRows(EmployeeScheduleShift $entry): array
    {
        if (! $entry->relationLoaded('availableRequests')) {
            return [];
        }

        return $entry->availableRequests
            ->filter(static fn (AvailableShiftRequest $request): bool => $request->status === AvailableShiftRequest::STATUS_PENDING)
            ->sortBy(static fn (AvailableShiftRequest $request): int => (int) $request->id)
            ->map(static function (AvailableShiftRequest $request): array {
                $employee = $request->relationLoaded('employee') ? $request->employee : null;

                return [
                    'id' => $request->id,
                    'employee_name' => $employee instanceof Employee
                        ? AdminWeeklySchedule::employeeDisplayName($employee)
                        : 'Employee',
                    'employee_public_id' => $employee instanceof Employee ? (string) $employee->public_id : '',
                    'note' => trim((string) ($request->note ?? '')),
                    'status' => $request->status,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Shifts offered to every employee in the mobile Available tab.
     *
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @param  Collection<int, AvailableShiftRequest>  $viewerRequests
     * @return list<array<string, mixed>>
     */
    public static function mobileItems(
        Collection $shifts,
        Employee $viewer,
        Collection $viewerRequests,
        string $today,
    ): array {
        $requestsByShift = $viewerRequests
            ->filter(static fn ($request): bool => $request instanceof AvailableShiftRequest)
            ->sortByDesc(static fn (AvailableShiftRequest $request): int => (int) $request->id)
            ->groupBy(static fn (AvailableShiftRequest $request): int => (int) $request->schedule_shift_id);

        return $shifts
            ->filter(static function (EmployeeScheduleShift $entry) use ($viewer): bool {
                return $entry->entry_type === EmployeeScheduleShift::TYPE_SHIFT
                    && $entry->isAvailableOffer()
                    && (int) $entry->employee_id !== (int) $viewer->id;
            })
            ->sortBy(static function (EmployeeScheduleShift $entry): string {
                return ($entry->scheduled_date?->toDateString() ?? '').'|'.(AdminWeeklySchedule::storedTimeToHm($entry->start_time) ?? '').'|'.$entry->id;
            })
            ->map(static function (EmployeeScheduleShift $entry) use ($viewer, $requestsByShift, $today): array {
                $assigned = $entry->cover_status === EmployeeScheduleShift::COVER_ASSIGNED;
                $assignee = self::coveringEmployee($entry);
                $assignedToViewer = $assignee instanceof Employee && (int) $assignee->id === (int) $viewer->id;
                $assigneeName = $assignee instanceof Employee
                    ? AdminWeeklySchedule::employeeDisplayName($assignee)
                    : '';
                $date = $entry->scheduled_date instanceof CarbonInterface
                    ? $entry->scheduled_date->toDateString()
                    : (string) $entry->scheduled_date;

                /** @var AvailableShiftRequest|null $mine */
                $mine = $requestsByShift->get((int) $entry->id)?->first();
                $pending = $mine instanceof AvailableShiftRequest
                    && $mine->status === AvailableShiftRequest::STATUS_PENDING;
                $open = $entry->cover_status === EmployeeScheduleShift::COVER_AVAILABLE;
                $canRequest = $open && ! $pending && $date !== '' && $date >= $today;

                $block = self::shiftSummary($entry);

                return [
                    ...$block,
                    'id' => $entry->id,
                    'scheduled_date' => $date,
                    'date_label' => $entry->scheduled_date instanceof CarbonInterface
                        ? $entry->scheduled_date->format('D, M j, Y')
                        : $date,
                    'original_employee_name' => self::originalEmployeeName($entry),
                    'status_label' => EmployeeScheduleShift::statusLabel($entry->status),
                    'state' => $assigned ? 'assigned' : 'open',
                    'assigned_to_you' => $assignedToViewer,
                    'assigned_employee_name' => $assigned ? $assigneeName : '',
                    'assignment_note' => $assigned ? self::assignmentNote($assigneeName, $assignedToViewer) : null,
                    'can_request' => $canRequest,
                    'my_request' => self::viewerRequestPayload($mine, $open),
                ];
            })
            ->values()
            ->all();
    }

    private static function coveringEmployee(EmployeeScheduleShift $entry): ?Employee
    {
        if (! $entry->relationLoaded('coveringShift')) {
            return null;
        }

        $covering = $entry->coveringShift;
        if (! $covering instanceof EmployeeScheduleShift || ! $covering->relationLoaded('employee')) {
            return null;
        }

        return $covering->employee instanceof Employee ? $covering->employee : null;
    }

    private static function originalEmployeeName(EmployeeScheduleShift $entry): string
    {
        if (! $entry->relationLoaded('employee') || ! $entry->employee instanceof Employee) {
            return '';
        }

        return AdminWeeklySchedule::employeeDisplayName($entry->employee);
    }

    /**
     * @return array<string, mixed>
     */
    private static function shiftSummary(EmployeeScheduleShift $entry): array
    {
        $start = AdminWeeklySchedule::storedTimeToHm($entry->start_time);
        $end = AdminWeeklySchedule::storedTimeToHm($entry->end_time);
        $jobTitle = trim((string) ($entry->relationLoaded('jobTitle') ? $entry->jobTitle?->name : ''));
        $department = trim((string) ($entry->relationLoaded('department') ? $entry->department?->name : ''));
        $location = trim((string) ($entry->relationLoaded('workLocation') ? $entry->workLocation?->name : ''));

        return [
            'time_range' => ($start && $end) ? $start.' – '.$end : '',
            'title' => $jobTitle !== '' ? $jobTitle : 'Shift',
            'meta' => trim(collect([$department, $location])->filter()->join(' · ')),
        ];
    }

    /**
     * An approved claim on a shift that is open again is stale, so the employee can request it.
     *
     * @return array<string, mixed>|null
     */
    private static function viewerRequestPayload(?AvailableShiftRequest $request, bool $shiftIsOpen): ?array
    {
        if (! $request instanceof AvailableShiftRequest) {
            return null;
        }

        if ($shiftIsOpen && $request->status === AvailableShiftRequest::STATUS_APPROVED) {
            return null;
        }

        return [
            'id' => $request->id,
            'status' => $request->status,
            'note' => $request->note,
            'decision_note' => $request->decision_note,
        ];
    }
}
