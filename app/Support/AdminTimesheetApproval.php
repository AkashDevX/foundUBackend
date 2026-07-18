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
     *     work_date: string,
     *     day_label: string,
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
        $approvalsBySession = $approvals->keyBy(static fn (TimesheetApproval $row): string => self::approvalSessionLookupKeyFor($row));

        $rows = [];

        foreach ($employees as $employee) {
            $entries = $employee->timeClockEntries ?? collect();
            if ($entries->isEmpty()) {
                continue;
            }

            $days = self::groupEntriesByDay($entries);

            foreach ($days as $workDate => $dayEntries) {
                $summary = AdminTimeClockDisplay::summarizeWorkSessions($dayEntries);
                if ($summary['total_seconds'] <= 0 && $summary['completed_sessions'] === 0) {
                    continue;
                }

                $sessionStatuses = [];
                foreach ($summary['hours_by_entry_id'] as $session) {
                    $clockInId = (int) ($session['clock_in_id'] ?? 0);
                    if ($clockInId <= 0) {
                        continue;
                    }

                    $approval = $approvalsBySession->get(self::approvalSessionLookupKey((int) $employee->id, $clockInId));
                    $sessionStatuses[] = $approval?->status ?? TimesheetApproval::STATUS_PENDING;
                }

                if ($sessionStatuses === []) {
                    continue;
                }

                $status = self::aggregateSessionStatuses($sessionStatuses);

                $workDateValue = Carbon::parse($workDate, DisplayTimezone::name())->startOfDay();

                if ($statusFilter !== null && $statusFilter !== 'all' && $status !== $statusFilter) {
                    continue;
                }

                $rows[] = [
                    'employee' => $employee,
                    'work_date' => $workDate,
                    'day_label' => self::formatDayLabel($workDateValue),
                    'total_seconds' => (int) $summary['total_seconds'],
                    'total_hours_label' => AdminTimeClockDisplay::formatDuration((int) $summary['total_seconds']),
                    'completed_sessions' => (int) $summary['completed_sessions'],
                    'status' => $status,
                    'status_label' => self::statusLabel($status),
                    'reviewed_by' => null,
                    'reviewed_at' => null,
                    'review_notes' => null,
                    'approval_id' => null,
                ];
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $dateCmp = strcmp($b['work_date'], $a['work_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
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
    public static function groupEntriesByDay(Collection $entries): array
    {
        $days = [];
        $tz = DisplayTimezone::name();

        foreach ($entries as $entry) {
            if ($entry->clocked_at === null) {
                continue;
            }

            $workDate = $entry->clocked_at
                ->copy()
                ->timezone($tz)
                ->toDateString();

            if (! isset($days[$workDate])) {
                $days[$workDate] = collect();
            }

            $days[$workDate]->push($entry);
        }

        return $days;
    }

    public static function normalizeWorkDate(string $workDate): string
    {
        return Carbon::parse($workDate, DisplayTimezone::name())->startOfDay()->toDateString();
    }

    public static function formatDayLabel(CarbonInterface $workDate): string
    {
        return DisplayTimezone::format($workDate, 'D, M j, Y');
    }

    public static function approvalLookupKey(TimesheetApproval $row): string
    {
        $employeeId = (int) ($row->getAttributes()['employee_id'] ?? $row->employee_id ?? 0);
        $workDateRaw = $row->getAttributes()['work_date'] ?? $row->work_date;
        if ($workDateRaw instanceof CarbonInterface) {
            $workDateRaw = $workDateRaw->toDateString();
        }

        return $employeeId.'|'.self::normalizeWorkDate((string) $workDateRaw);
    }

    public static function approvalLookupKeyFor(int $employeeId, string $workDate): string
    {
        return $employeeId.'|'.self::normalizeWorkDate($workDate);
    }

    public static function approvalSessionLookupKey(int $employeeId, int $clockInEntryId): string
    {
        return $employeeId.'|ci|'.$clockInEntryId;
    }

    public static function approvalSessionLookupKeyFor(TimesheetApproval $row): string
    {
        return self::approvalSessionLookupKey(
            (int) ($row->getAttributes()['employee_id'] ?? $row->employee_id ?? 0),
            (int) ($row->getAttributes()['clock_in_entry_id'] ?? $row->clock_in_entry_id ?? 0),
        );
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $dayEntries
     */
    public static function resolveSessionClockInId(Collection $dayEntries, TimeClockEntry $entry): ?int
    {
        $summary = AdminTimeClockDisplay::summarizeWorkSessions($dayEntries);

        foreach ($summary['hours_by_entry_id'] as $entryId => $session) {
            $clockInId = (int) ($session['clock_in_id'] ?? 0);
            $clockOutId = isset($session['clock_out_id']) ? (int) $session['clock_out_id'] : null;

            if ($entry->id === $entryId || $entry->id === $clockInId || ($clockOutId !== null && $entry->id === $clockOutId)) {
                return $clockInId > 0 ? $clockInId : null;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TimesheetApproval>  $approvals
     * @return Collection<int, string>
     */
    public static function approvedSessionKeys(Collection $approvals): Collection
    {
        return $approvals
            ->where('status', TimesheetApproval::STATUS_APPROVED)
            ->map(static fn (TimesheetApproval $approval): string => self::approvalSessionLookupKeyFor($approval))
            ->values();
    }

    public static function sessionSummaryForClockIn(array $summary, int $clockInEntryId): ?array
    {
        foreach ($summary['hours_by_entry_id'] as $session) {
            if ((int) ($session['clock_in_id'] ?? 0) === $clockInEntryId) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $dayEntries
     * @return Collection<int, TimeClockEntry>
     */
    public static function sessionEntriesForClockIn(Collection $dayEntries, int $clockInEntryId): Collection
    {
        $clockIn = $dayEntries->first(static fn (TimeClockEntry $entry): bool => (int) $entry->id === $clockInEntryId
            && $entry->event_type === TimeClockEntry::EVENT_CLOCK_IN);

        if (! $clockIn instanceof TimeClockEntry) {
            return collect();
        }

        $summary = AdminTimeClockDisplay::summarizeWorkSessions($dayEntries);
        $session = self::sessionSummaryForClockIn($summary, $clockInEntryId);

        if (! is_array($session)) {
            return collect([$clockIn]);
        }

        $entries = collect([$clockIn]);
        $clockOutId = $session['clock_out_id'] ?? null;
        if ($clockOutId !== null) {
            $clockOut = $dayEntries->first(static fn (TimeClockEntry $entry): bool => (int) $entry->id === (int) $clockOutId);
            if ($clockOut instanceof TimeClockEntry) {
                $entries->push($clockOut);
            }
        }

        return $entries->values();
    }

    /**
     * @param  list<string>  $statuses
     */
    public static function aggregateSessionStatuses(array $statuses): string
    {
        if ($statuses === []) {
            return TimesheetApproval::STATUS_PENDING;
        }

        if (in_array(TimesheetApproval::STATUS_PENDING, $statuses, true)) {
            return TimesheetApproval::STATUS_PENDING;
        }

        if (in_array(TimesheetApproval::STATUS_REJECTED, $statuses, true)) {
            return TimesheetApproval::STATUS_REJECTED;
        }

        return TimesheetApproval::STATUS_APPROVED;
    }
}
