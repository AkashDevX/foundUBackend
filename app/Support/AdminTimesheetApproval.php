<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimesheetApproval;
use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AdminTimesheetApproval
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, TimesheetApproval>  $approvals
     * @return list<array{
     *     employee: Employee,
     *     week_start: string,
     *     week_end: string,
     *     week_label: string,
     *     total_seconds: int,
     *     total_hours_label: string,
     *     completed_sessions: int,
     *     status: string,
     *     status_label: string,
     *     reviewed_by: string|null,
     *     reviewed_at: CarbonInterface|null,
     *     review_notes: string|null,
     *     approval_id: int|null,
     * }>
     */
    public static function buildRows(Collection $employees, Collection $approvals, ?string $statusFilter = null): array
    {
        $approvalKey = static function (int $employeeId, string $weekStart): string {
            return $employeeId.'|'.$weekStart;
        };

        $approvalsByKey = $approvals->keyBy(static fn (TimesheetApproval $row): string => self::approvalLookupKey($row));

        $rows = [];

        foreach ($employees as $employee) {
            $entries = $employee->timeClockEntries ?? collect();
            if ($entries->isEmpty()) {
                continue;
            }

            $weeks = self::groupEntriesByWeek($entries);

            foreach ($weeks as $weekStart => $weekEntries) {
                $summary = AdminTimeClockDisplay::summarizeWorkSessions($weekEntries);
                if ($summary['total_seconds'] <= 0 && $summary['completed_sessions'] === 0) {
                    continue;
                }

                $weekStartDate = Carbon::parse($weekStart, DisplayTimezone::name())->startOfDay();
                $weekEndDate = $weekStartDate->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay();

                /** @var TimesheetApproval|null $approval */
                $approval = $approvalsByKey->get($approvalKey((int) $employee->id, $weekStart));
                $status = $approval?->status ?? TimesheetApproval::STATUS_PENDING;

                if ($statusFilter !== null && $statusFilter !== 'all' && $status !== $statusFilter) {
                    continue;
                }

                $rows[] = [
                    'employee' => $employee,
                    'week_start' => $weekStart,
                    'week_end' => $weekEndDate->toDateString(),
                    'week_label' => self::formatWeekLabel($weekStartDate, $weekEndDate),
                    'total_seconds' => (int) $summary['total_seconds'],
                    'total_hours_label' => AdminTimeClockDisplay::formatDuration((int) $summary['total_seconds']),
                    'completed_sessions' => (int) $summary['completed_sessions'],
                    'status' => $status,
                    'status_label' => self::statusLabel($status),
                    'reviewed_by' => $approval?->reviewed_by,
                    'reviewed_at' => $approval?->reviewed_at,
                    'review_notes' => $approval?->review_notes,
                    'approval_id' => $approval?->id,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $weekCmp = strcmp($b['week_start'], $a['week_start']);
            if ($weekCmp !== 0) {
                return $weekCmp;
            }

            return strcasecmp(
                (string) ($a['employee']->full_legal_name ?? $a['employee']->email),
                (string) ($b['employee']->full_legal_name ?? $b['employee']->email)
            );
        });

        return $rows;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            TimesheetApproval::STATUS_APPROVED => 'Approved',
            TimesheetApproval::STATUS_REJECTED => 'Rejected',
            default => 'Pending approval',
        };
    }

    public static function statusBadgeClasses(string $status): string
    {
        return match ($status) {
            TimesheetApproval::STATUS_APPROVED => 'bg-emerald-50 text-emerald-800 ring-emerald-200',
            TimesheetApproval::STATUS_REJECTED => 'bg-red-50 text-red-800 ring-red-200',
            default => 'bg-amber-50 text-amber-900 ring-amber-200',
        };
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @return array<string, Collection<int, TimeClockEntry>>
     */
    public static function groupEntriesByWeek(Collection $entries): array
    {
        $weeks = [];

        foreach ($entries as $entry) {
            if ($entry->clocked_at === null) {
                continue;
            }

            $weekStart = $entry->clocked_at
                ->copy()
                ->timezone(DisplayTimezone::name())
                ->startOfWeek(Carbon::MONDAY)
                ->toDateString();

            if (! isset($weeks[$weekStart])) {
                $weeks[$weekStart] = collect();
            }

            $weeks[$weekStart]->push($entry);
        }

        return $weeks;
    }

    public static function normalizeWeekStart(string $weekStart): string
    {
        $date = Carbon::parse($weekStart, DisplayTimezone::name())->startOfDay();

        return $date->startOfWeek(Carbon::MONDAY)->toDateString();
    }

    public static function weekEndForStart(string $weekStart): string
    {
        return Carbon::parse($weekStart, DisplayTimezone::name())
            ->startOfWeek(Carbon::MONDAY)
            ->endOfWeek(Carbon::SUNDAY)
            ->toDateString();
    }

    public static function formatWeekLabel(CarbonInterface $weekStart, CarbonInterface $weekEnd): string
    {
        return sprintf(
            '%s – %s',
            DisplayTimezone::format($weekStart, 'M j, Y'),
            DisplayTimezone::format($weekEnd, 'M j, Y')
        );
    }

    public static function approvalLookupKey(TimesheetApproval $row): string
    {
        $employeeId = (int) ($row->getAttributes()['employee_id'] ?? $row->employee_id ?? 0);
        $weekStartRaw = $row->getAttributes()['week_start'] ?? $row->week_start;
        if ($weekStartRaw instanceof CarbonInterface) {
            $weekStartRaw = $weekStartRaw->toDateString();
        }

        return $employeeId.'|'.self::normalizeWeekStart((string) $weekStartRaw);
    }
}
