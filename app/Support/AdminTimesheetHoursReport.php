<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AdminTimesheetHoursReport
{
    /**
     * Build a payroll-oriented timesheet: one row per clocked shift, plus an
     * employee period summary.
     *
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, TimesheetApproval>  $approvals
     * @return array{
     *     shifts: list<array<string, mixed>>,
     *     summaries: list<array<string, mixed>>,
     *     stats: array{hours: float, employees: int, shifts: int, days: int},
     * }
     */
    public static function build(
        Collection $employees,
        CarbonInterface $from,
        CarbonInterface $to,
        Collection $approvals,
        ?string $statusFilter = null,
    ): array {
        $tz = DisplayTimezone::name();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $approvalsBySession = $approvals->keyBy(
            static fn (TimesheetApproval $approval): string => AdminTimesheetApproval::approvalSessionLookupKeyFor($approval)
        );

        $shifts = [];

        foreach ($employees as $employee) {
            $entries = $employee->timeClockEntries ?? collect();
            if ($entries->isEmpty()) {
                continue;
            }

            $summary = AdminTimeClockDisplay::summarizeWorkSessions($entries);
            $entriesById = $entries->keyBy(
                static fn (TimeClockEntry $entry): int => (int) $entry->id
            );

            foreach ($summary['hours_by_entry_id'] as $session) {
                $clockInId = (int) ($session['clock_in_id'] ?? 0);
                $clockIn = $entriesById->get($clockInId);
                if (! $clockIn instanceof TimeClockEntry || $clockIn->clocked_at === null) {
                    continue;
                }

                $workDate = $clockIn->clocked_at->copy()->timezone($tz)->toDateString();
                if ($workDate < $fromDate || $workDate > $toDate) {
                    continue;
                }

                $approvalKey = AdminTimesheetApproval::approvalSessionLookupKey((int) $employee->id, $clockInId);
                $approval = $approvalsBySession->get($approvalKey);
                $status = $approval?->status ?? TimesheetApproval::STATUS_PENDING;

                if ($statusFilter !== null && $statusFilter !== '' && $status !== $statusFilter) {
                    continue;
                }

                $clockOutId = isset($session['clock_out_id']) ? (int) $session['clock_out_id'] : 0;
                $clockOut = $clockOutId > 0 ? $entriesById->get($clockOutId) : null;
                $isOpen = (bool) ($session['is_open'] ?? false);
                $seconds = (int) ($session['seconds'] ?? 0);

                if ($approval instanceof TimesheetApproval && (int) $approval->total_seconds > 0) {
                    $seconds = (int) $approval->total_seconds;
                }

                $finishTime = '—';
                if ($clockOut instanceof TimeClockEntry && $clockOut->clocked_at !== null) {
                    $finishTime = DisplayTimezone::format($clockOut->clocked_at, 'g:i A');
                } elseif ($isOpen) {
                    $finishTime = 'In progress';
                }

                $shifts[] = [
                    'employee' => self::employeeName($employee),
                    'employee_id' => (int) $employee->id,
                    'work_date' => $workDate,
                    'date_label' => Carbon::parse($workDate, $tz)->format('d/m/Y'),
                    'start_time' => DisplayTimezone::format($clockIn->clocked_at, 'g:i A'),
                    'finish_time' => $finishTime,
                    'hours' => round(max(0, $seconds) / 3600, 2),
                    'seconds' => max(0, $seconds),
                    'status' => $status,
                    'status_label' => AdminTimesheetApproval::statusLabel($status),
                    'is_open' => $isOpen,
                    'sort_name' => mb_strtolower(self::employeeName($employee)),
                    'sort_time' => $clockIn->clocked_at->copy()->timezone($tz)->format('H:i:s'),
                ];
            }
        }

        usort($shifts, static function (array $a, array $b): int {
            $nameCmp = strcmp((string) $a['sort_name'], (string) $b['sort_name']);
            if ($nameCmp !== 0) {
                return $nameCmp;
            }

            $dateCmp = strcmp((string) $a['work_date'], (string) $b['work_date']);
            if ($dateCmp !== 0) {
                return $dateCmp;
            }

            return strcmp((string) $a['sort_time'], (string) $b['sort_time']);
        });

        $summaries = self::summarizeByEmployee($shifts);
        $dayKeys = [];
        foreach ($shifts as $shift) {
            $dayKeys[$shift['employee_id'].'|'.$shift['work_date']] = true;
        }

        return [
            'shifts' => $shifts,
            'summaries' => $summaries,
            'stats' => [
                'hours' => round(array_sum(array_column($shifts, 'hours')), 2),
                'employees' => count($summaries),
                'shifts' => count($shifts),
                'days' => count($dayKeys),
            ],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $shifts
     * @return list<array{employee: string, days: int, sessions: int, hours: float}>
     */
    private static function summarizeByEmployee(array $shifts): array
    {
        $grouped = [];

        foreach ($shifts as $shift) {
            $id = (int) $shift['employee_id'];
            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'employee' => (string) $shift['employee'],
                    'sort_name' => (string) $shift['sort_name'],
                    'days' => [],
                    'sessions' => 0,
                    'hours' => 0.0,
                ];
            }

            $grouped[$id]['days'][(string) $shift['work_date']] = true;
            $grouped[$id]['sessions']++;
            $grouped[$id]['hours'] += (float) $shift['hours'];
        }

        $summaries = [];
        foreach ($grouped as $row) {
            $summaries[] = [
                'employee' => $row['employee'],
                'days' => count($row['days']),
                'sessions' => $row['sessions'],
                'hours' => round($row['hours'], 2),
                'sort_name' => $row['sort_name'],
            ];
        }

        usort($summaries, static fn (array $a, array $b): int => strcmp($a['sort_name'], $b['sort_name']));

        return array_map(static function (array $row): array {
            unset($row['sort_name']);

            return $row;
        }, $summaries);
    }

    private static function employeeName(Employee $employee): string
    {
        $name = trim((string) ($employee->full_legal_name
            ?: trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))));

        if ($name !== '') {
            return $name;
        }

        $email = trim((string) ($employee->email ?? ''));

        return $email !== '' ? $email : ('Employee #'.$employee->id);
    }
}
