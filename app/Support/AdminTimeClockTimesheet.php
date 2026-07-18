<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimesheetApproval;
use App\Models\TimeClockEntry;
use App\Support\PayrollRateTypes;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class AdminTimeClockTimesheet
{
    /** @var list<array{bg: string, border: string, text: string, accent: string}> */
    private const POSITION_PALETTES = [
        ['bg' => 'bg-emerald-50', 'border' => 'border-emerald-200', 'text' => 'text-emerald-800', 'accent' => 'bg-emerald-500'],
        ['bg' => 'bg-sky-50', 'border' => 'border-sky-200', 'text' => 'text-sky-800', 'accent' => 'bg-sky-500'],
        ['bg' => 'bg-amber-50', 'border' => 'border-amber-200', 'text' => 'text-amber-800', 'accent' => 'bg-amber-500'],
        ['bg' => 'bg-violet-50', 'border' => 'border-violet-200', 'text' => 'text-violet-800', 'accent' => 'bg-violet-500'],
        ['bg' => 'bg-orange-50', 'border' => 'border-orange-200', 'text' => 'text-orange-800', 'accent' => 'bg-orange-500'],
        ['bg' => 'bg-slate-100', 'border' => 'border-slate-300', 'text' => 'text-slate-700', 'accent' => 'bg-slate-400'],
    ];

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @param  Collection<int, TimesheetApproval>  $timesheetApprovals
     * @return array{
     *     groups: list<array<string, mixed>>,
     *     stats: array<string, int|string>,
     * }
     */
    public static function buildGroups(
        Collection $employees,
        CarbonInterface $weekStart,
        Collection $scheduleShifts,
        Collection $timesheetApprovals,
        ?string $statusFilter = null,
    ): array {
        $weekEnd = $weekStart->copy()->addDays(6);
        $weekStartString = $weekStart->toDateString();
        $weekEndString = $weekEnd->toDateString();
        $tz = DisplayTimezone::name();

        $approvalsBySession = $timesheetApprovals->keyBy(
            static fn (TimesheetApproval $approval): string => AdminTimesheetApproval::approvalSessionLookupKeyFor($approval)
        );

        $scheduleByEmployee = $scheduleShifts
            ->filter(static fn (EmployeeScheduleShift $shift): bool => $shift->entry_type === EmployeeScheduleShift::TYPE_SHIFT)
            ->groupBy(static fn (EmployeeScheduleShift $shift): int => (int) $shift->employee_id);

        $groups = [];
        $totalRows = 0;
        $pendingCount = 0;
        $approvedCount = 0;

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $entries = $employee->timeClockEntries ?? collect();
            $weekEntries = $entries->filter(static function (TimeClockEntry $entry) use ($weekStartString, $weekEndString, $tz): bool {
                if ($entry->clocked_at === null) {
                    return false;
                }

                $date = $entry->clocked_at->copy()->timezone($tz)->toDateString();

                return $date >= $weekStartString && $date <= $weekEndString;
            })->values();

            $employeeSchedule = $scheduleByEmployee
                ->get($employeeId, collect())
                ->filter(static function (EmployeeScheduleShift $shift) use ($weekStartString, $weekEndString): bool {
                    $date = $shift->scheduled_date?->toDateString();
                    if ($date === null) {
                        return false;
                    }

                    return $date >= $weekStartString && $date <= $weekEndString;
                })
                ->sortBy([
                static fn (EmployeeScheduleShift $shift) => $shift->scheduled_date?->toDateString() ?? '',
                static fn (EmployeeScheduleShift $shift) => self::storedTimeToHm($shift->start_time),
                static fn (EmployeeScheduleShift $shift) => $shift->id,
            ])->values();

            if ($weekEntries->isEmpty() && $employeeSchedule->isEmpty()) {
                continue;
            }

            $sessions = self::buildWorkSessions($weekEntries);
            $usedSessionIndexes = [];
            $rows = [];

            foreach ($employeeSchedule as $scheduleShift) {
                $sessionIndex = self::matchSessionIndex($sessions, $scheduleShift, $usedSessionIndexes, $tz);
                $session = $sessionIndex !== null ? $sessions[$sessionIndex] : null;

                $workDate = $scheduleShift->scheduled_date?->toDateString()
                    ?? ($session['date'] ?? '');

                if ($session === null && $workDate !== '') {
                    $session = self::findSessionForWorkDate($sessions, $workDate);
                }

                if ($sessionIndex !== null) {
                    $usedSessionIndexes[] = $sessionIndex;
                }

                if ($session === null) {
                    continue;
                }

                $status = self::resolveSessionStatus($employeeId, $session, $approvalsBySession);
                $reviewNotes = self::sessionReviewNotes($employeeId, $session, $approvalsBySession);

                $rows[] = self::buildRow($employee, $scheduleShift, $session, $status, $workDate, $reviewNotes);
            }

            foreach ($sessions as $index => $session) {
                if (in_array($index, $usedSessionIndexes, true)) {
                    continue;
                }

                $workDate = (string) ($session['date'] ?? '');
                $status = self::resolveSessionStatus($employeeId, $session, $approvalsBySession);
                $reviewNotes = self::sessionReviewNotes($employeeId, $session, $approvalsBySession);

                $rows[] = self::buildRow($employee, null, $session, $status, $workDate, $reviewNotes);
            }

            if ($statusFilter !== null && $statusFilter !== 'all') {
                $rows = array_values(array_filter(
                    $rows,
                    static fn (array $row): bool => ($row['status'] ?? TimesheetApproval::STATUS_PENDING) === $statusFilter
                ));
            }

            if ($rows === []) {
                continue;
            }

            usort($rows, static function (array $a, array $b): int {
                $dateCmp = strcmp((string) ($a['sort_date'] ?? ''), (string) ($b['sort_date'] ?? ''));
                if ($dateCmp !== 0) {
                    return $dateCmp;
                }

                return strcmp((string) ($a['sort_time'] ?? ''), (string) ($b['sort_time'] ?? ''));
            });

            $summary = self::summarizeRows($rows);
            $pendingDays = count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['status'] ?? '') === TimesheetApproval::STATUS_PENDING
            ));
            $approvedDays = count(array_filter(
                $rows,
                static fn (array $row): bool => ($row['status'] ?? '') === TimesheetApproval::STATUS_APPROVED
            ));

            $pendingCount += $pendingDays;
            $approvedCount += $approvedDays;
            $totalRows += count($rows);

            $employmentType = PayrollRateTypes::employmentTypeLabel($employee->employment_type);

            $groups[] = [
                'employee' => $employee,
                'employee_public_id' => $employee->public_id,
                'name' => AdminWeeklySchedule::employeeDisplayName($employee),
                'initials' => AdminWeeklySchedule::employeeInitials($employee),
                'employment_type' => $employmentType,
                'employment_type_palette' => self::positionPalette($employmentType),
                'summary' => $summary,
                'pending_days' => $pendingDays,
                'approved_days' => $approvedDays,
                'shift_rows' => count($rows),
                'rows' => $rows,
            ];
        }

        usort($groups, static fn (array $a, array $b): int => strcasecmp((string) $a['name'], (string) $b['name']));

        return [
            'groups' => $groups,
            'stats' => [
                'employees' => count($groups),
                'rows' => $totalRows,
                'pending' => $pendingCount,
                'approved' => $approvedCount,
            ],
        ];
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @param  Collection<int, TimesheetApproval>  $timesheetApprovals
     * @return list<array{
     *     week_start: string,
     *     week_label: string,
     *     week_label_long: string,
     *     is_current: bool,
     *     stats: array<string, int|string>,
     * }>
     */
    public static function buildWeekIndex(
        Collection $employees,
        Collection $scheduleShifts,
        Collection $timesheetApprovals,
        int $weekCount = 12,
    ): array {
        $currentWeek = AdminWeeklySchedule::resolveWeekStart(null);
        $weeks = [];

        for ($offset = 0; $offset < $weekCount; $offset++) {
            $weekStart = $currentWeek->copy()->subWeeks($offset);
            $built = self::buildGroups($employees, $weekStart, $scheduleShifts, $timesheetApprovals, null);

            $weeks[] = [
                'week_start' => $weekStart->toDateString(),
                'week_label' => self::formatCompactWeekLabel($weekStart),
                'week_label_long' => AdminWeeklySchedule::formatWeekLabel($weekStart),
                'is_current' => $offset === 0,
                'stats' => $built['stats'],
            ];
        }

        return $weeks;
    }

    public static function formatCompactWeekLabel(CarbonInterface $weekStart): string
    {
        $weekEnd = $weekStart->copy()->addDays(6);
        $tz = DisplayTimezone::name();
        $start = $weekStart->copy()->timezone($tz);
        $end = $weekEnd->copy()->timezone($tz);

        return sprintf(
            '%s - %s',
            $start->format('j M'),
            $end->format('j M')
        );
    }

    public static function formatDecimalHours(int $seconds): string
    {
        return number_format(max(0, $seconds) / 3600, 2, '.', '');
    }

    public static function formatDecimalHoursFromFloat(float $hours): string
    {
        return number_format($hours, 2, '.', '');
    }

    /**
     * @return array{bg: string, border: string, text: string, accent: string}
     */
    public static function positionPalette(string $position): array
    {
        $index = abs(crc32(mb_strtolower(trim($position)))) % count(self::POSITION_PALETTES);

        return self::POSITION_PALETTES[$index];
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @return list<array{
     *     clock_in: TimeClockEntry,
     *     clock_out: TimeClockEntry|null,
     *     seconds: int,
     *     is_open: bool,
     *     date: string,
     * }>
     */
    private static function buildWorkSessions(Collection $entries): array
    {
        $sorted = $entries
            ->sortBy(static fn (TimeClockEntry $entry) => [
                $entry->clocked_at?->getTimestamp() ?? 0,
                $entry->id,
            ])
            ->values();

        $sessions = [];
        $openClockIn = null;
        $tz = DisplayTimezone::name();

        foreach ($sorted as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                $openClockIn = $entry;

                continue;
            }

            if ($entry->event_type !== TimeClockEntry::EVENT_CLOCK_OUT || $openClockIn === null) {
                continue;
            }

            $seconds = (int) $openClockIn->clocked_at?->diffInSeconds($entry->clocked_at);
            $sessions[] = [
                'clock_in' => $openClockIn,
                'clock_out' => $entry,
                'seconds' => $seconds,
                'is_open' => false,
                'date' => $openClockIn->clocked_at?->copy()->timezone($tz)->toDateString() ?? '',
            ];
            $openClockIn = null;
        }

        if ($openClockIn instanceof TimeClockEntry && $openClockIn->clocked_at !== null) {
            $sessions[] = [
                'clock_in' => $openClockIn,
                'clock_out' => null,
                'seconds' => (int) $openClockIn->clocked_at->diffInSeconds(now('UTC')),
                'is_open' => true,
                'date' => $openClockIn->clocked_at->copy()->timezone($tz)->toDateString(),
            ];
        }

        return $sessions;
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @param  list<int>  $usedSessionIndexes
     */
    private static function matchSessionIndex(array $sessions, EmployeeScheduleShift $scheduleShift, array $usedSessionIndexes, string $tz): ?int
    {
        $scheduleDate = $scheduleShift->scheduled_date?->toDateString();
        if ($scheduleDate === null) {
            return null;
        }

        $scheduleStartMinutes = self::timeToMinutes(self::storedTimeToHm($scheduleShift->start_time));
        $bestIndex = null;
        $bestDistance = PHP_INT_MAX;

        foreach ($sessions as $index => $session) {
            if (in_array($index, $usedSessionIndexes, true)) {
                continue;
            }

            if (($session['date'] ?? '') !== $scheduleDate) {
                continue;
            }

            $clockIn = $session['clock_in'] ?? null;
            if (! $clockIn instanceof TimeClockEntry || $clockIn->clocked_at === null) {
                continue;
            }

            $clockMinutes = (int) $clockIn->clocked_at->copy()->timezone($tz)->format('G') * 60
                + (int) $clockIn->clocked_at->copy()->timezone($tz)->format('i');

            $distance = $scheduleStartMinutes === null
                ? 0
                : abs($clockMinutes - $scheduleStartMinutes);

            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * @param  list<array<string, mixed>>  $sessions
     * @return array<string, mixed>|null
     */
    private static function findSessionForWorkDate(array $sessions, string $workDate): ?array
    {
        foreach ($sessions as $session) {
            if (($session['date'] ?? '') === $workDate) {
                return $session;
            }
        }

        return null;
    }

    /**
     * @param  Collection<string, TimesheetApproval>  $approvalsBySession
     */
    private static function resolveSessionStatus(int $employeeId, ?array $session, Collection $approvalsBySession): string
    {
        $clockIn = $session['clock_in'] ?? null;
        if (! $clockIn instanceof TimeClockEntry || (int) $clockIn->id <= 0) {
            return TimesheetApproval::STATUS_PENDING;
        }

        $key = AdminTimesheetApproval::approvalSessionLookupKey($employeeId, (int) $clockIn->id);

        return $approvalsBySession->get($key)?->status ?? TimesheetApproval::STATUS_PENDING;
    }

    /**
     * @param  Collection<string, TimesheetApproval>  $approvalsBySession
     */
    private static function sessionReviewNotes(int $employeeId, ?array $session, Collection $approvalsBySession): ?string
    {
        $clockIn = $session['clock_in'] ?? null;
        if (! $clockIn instanceof TimeClockEntry || (int) $clockIn->id <= 0) {
            return null;
        }

        $key = AdminTimesheetApproval::approvalSessionLookupKey($employeeId, (int) $clockIn->id);

        return $approvalsBySession->get($key)?->review_notes;
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @return array<string, mixed>
     */
    private static function buildRow(
        Employee $employee,
        ?EmployeeScheduleShift $scheduleShift,
        ?array $session,
        string $status,
        string $workDate,
        ?string $reviewNotes = null,
    ): array {
        $tz = DisplayTimezone::name();
        $scheduledSeconds = $scheduleShift !== null ? self::scheduleShiftDurationSeconds($scheduleShift) : 0;
        $workedSeconds = (int) ($session['seconds'] ?? 0);
        $differenceSeconds = $workedSeconds - $scheduledSeconds;

        $employmentType = PayrollRateTypes::employmentTypeLabel($employee->employment_type);
        $location = self::resolveLocationLabel($employee, $scheduleShift, $session);

        $clockIn = $session['clock_in'] ?? null;
        $clockOut = $session['clock_out'] ?? null;

        $date = $workDate !== ''
            ? $workDate
            : ($scheduleShift?->scheduled_date?->toDateString() ?? ($session['date'] ?? ''));

        $autoClockOut = '—';
        if ($clockOut instanceof TimeClockEntry) {
            $autoClockOut = $clockOut->punch_source === TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT ? 'Yes' : 'No';
        }

        $scheduledStart = $scheduleShift !== null ? self::formatStoredTime($scheduleShift->start_time) : '—';
        $scheduledEnd = $scheduleShift !== null ? self::formatStoredTime($scheduleShift->end_time) : '—';
        $scheduledTimeLabel = $scheduledStart !== '—' && $scheduledEnd !== '—'
            ? $scheduledStart.' – '.$scheduledEnd
            : '—';

        $clockInLabel = $clockIn instanceof TimeClockEntry && $clockIn->clocked_at !== null
            ? DisplayTimezone::format($clockIn->clocked_at, 'g:i A')
            : '—';
        $clockOutLabel = $clockOut instanceof TimeClockEntry && $clockOut->clocked_at !== null
            ? DisplayTimezone::format($clockOut->clocked_at, 'g:i A')
            : (($session['is_open'] ?? false) ? 'In progress' : '—');
        $actualTimeLabel = $clockInLabel !== '—'
            ? ($clockOutLabel !== '—' ? $clockInLabel.' – '.$clockOutLabel : $clockInLabel.($clockOutLabel === 'In progress' ? ' – In progress' : ''))
            : '—';

        $dateLabelLong = $date !== ''
            ? DisplayTimezone::format(Carbon::parse($date, $tz), 'l, M j, Y')
            : '—';

        return [
            'employment_type' => $employmentType,
            'employment_type_palette' => self::positionPalette($employmentType),
            'location' => $location,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'clock_in' => $clockInLabel,
            'clock_in_map' => AdminTimeClockDisplay::punchMapPayload($clockIn instanceof TimeClockEntry ? $clockIn : null),
            'clock_in_distance_meters' => AdminTimeClockDisplay::formatDistanceMetersInteger(
                $clockIn instanceof TimeClockEntry ? $clockIn : null
            ),
            'break_start' => '—',
            'break_end' => '—',
            'break_duration_hours' => '—',
            'clock_out' => $clockOutLabel,
            'clock_out_map' => AdminTimeClockDisplay::punchMapPayload($clockOut instanceof TimeClockEntry ? $clockOut : null),
            'scheduled_duration_hours' => $scheduledSeconds > 0 ? self::formatDecimalHours($scheduledSeconds) : '—',
            'worked_duration_hours' => $workedSeconds > 0 ? self::formatDecimalHours($workedSeconds) : '—',
            'difference_hours' => ($scheduledSeconds > 0 || $workedSeconds > 0)
                ? self::formatDecimalHours($differenceSeconds)
                : '—',
            'difference_is_alert' => abs($differenceSeconds) >= 60,
            'date_label' => $date !== ''
                ? DisplayTimezone::format(Carbon::parse($date, $tz), 'M j')
                : '—',
            'status' => $status,
            'status_label' => AdminTimesheetApproval::statusLabel($status),
            'status_badge_classes' => AdminTimesheetApproval::statusBadgeClasses($status),
            'can_review' => in_array($status, [TimesheetApproval::STATUS_PENDING, TimesheetApproval::STATUS_REJECTED], true),
            'can_reset' => in_array($status, [TimesheetApproval::STATUS_APPROVED, TimesheetApproval::STATUS_REJECTED], true),
            'work_date' => $date,
            'auto_clock_out' => $autoClockOut,
            'break_type' => $scheduleShift !== null ? 'Standard' : '—',
            'sort_date' => $date,
            'sort_time' => $scheduleShift !== null
                ? self::storedTimeToHm($scheduleShift->start_time)
                : ($clockIn instanceof TimeClockEntry && $clockIn->clocked_at !== null
                    ? $clockIn->clocked_at->copy()->timezone($tz)->format('H:i')
                    : '99:99'),
            'is_open' => (bool) ($session['is_open'] ?? false),
            'modal' => [
                'employee_public_id' => $employee->public_id,
                'employee_name' => AdminWeeklySchedule::employeeDisplayName($employee),
                'work_date' => $date,
                'date_label' => $dateLabelLong,
                'location' => $location,
                'position' => $employmentType,
                'scheduled_time' => $scheduledTimeLabel,
                'actual_time' => $actualTimeLabel,
                'clock_in_at' => AdminTimeClockDisplay::toDatetimeLocalValue(
                    $clockIn instanceof TimeClockEntry ? $clockIn->clocked_at : null
                ),
                'clock_out_at' => AdminTimeClockDisplay::toDatetimeLocalValue(
                    $clockOut instanceof TimeClockEntry ? $clockOut->clocked_at : null
                ),
                'clock_in_entry_id' => $clockIn instanceof TimeClockEntry ? $clockIn->id : null,
                'clock_out_entry_id' => $clockOut instanceof TimeClockEntry ? $clockOut->id : null,
                'clock_out_comment' => $clockOut instanceof TimeClockEntry
                    ? trim((string) ($clockOut->comment ?? ''))
                    : '',
                'review_notes' => $reviewNotes ?? '',
                'status' => $status,
                'status_label' => AdminTimesheetApproval::statusLabel($status),
                'can_review' => in_array($status, [TimesheetApproval::STATUS_PENDING, TimesheetApproval::STATUS_REJECTED], true),
                'can_reset' => in_array($status, [TimesheetApproval::STATUS_APPROVED, TimesheetApproval::STATUS_REJECTED], true),
                'is_open' => (bool) ($session['is_open'] ?? false),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $session
     */
    private static function resolveLocationLabel(Employee $employee, ?EmployeeScheduleShift $scheduleShift, ?array $session): string
    {
        if ($scheduleShift !== null) {
            $fromSchedule = trim((string) ($scheduleShift->workLocation?->name ?? ''));
            if ($fromSchedule !== '') {
                return $fromSchedule;
            }
        }

        $clockIn = $session['clock_in'] ?? null;
        if ($clockIn instanceof TimeClockEntry) {
            $fromEntry = trim((string) ($clockIn->workLocation?->name ?? ''));
            if ($fromEntry !== '') {
                return $fromEntry;
            }
        }

        return trim((string) ($employee->workLocation?->name ?? '')) ?: '—';
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private static function summarizeRows(array $rows): array
    {
        $scheduledSeconds = 0;
        $workedSeconds = 0;

        foreach ($rows as $row) {
            if (($row['scheduled_duration_hours'] ?? '—') !== '—') {
                $scheduledSeconds += (int) round(((float) $row['scheduled_duration_hours']) * 3600);
            }
            if (($row['worked_duration_hours'] ?? '—') !== '—') {
                $workedSeconds += (int) round(((float) $row['worked_duration_hours']) * 3600);
            }
        }

        $differenceSeconds = $workedSeconds - $scheduledSeconds;

        return [
            'break_duration_hours' => '—',
            'scheduled_duration_hours' => $scheduledSeconds > 0 ? self::formatDecimalHours($scheduledSeconds) : '—',
            'worked_duration_hours' => $workedSeconds > 0 ? self::formatDecimalHours($workedSeconds) : '—',
            'difference_hours' => ($scheduledSeconds > 0 || $workedSeconds > 0)
                ? self::formatDecimalHours($differenceSeconds)
                : '—',
            'difference_is_alert' => abs($differenceSeconds) >= 60,
        ];
    }

    private static function scheduleShiftDurationSeconds(EmployeeScheduleShift $shift): int
    {
        return self::timeRangeDurationSeconds(
            self::storedTimeToHm($shift->start_time),
            self::storedTimeToHm($shift->end_time)
        );
    }

    private static function formatStoredTime(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('g:i A');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return self::formatTimeString(sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]));
        }

        return '—';
    }

    private static function formatTimeString(string $hm): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $hm, $matches)) {
            return '—';
        }

        $hour24 = (int) $matches[1];
        $mins = (int) $matches[2];
        $period = $hour24 >= 12 ? 'PM' : 'AM';
        $hour12 = $hour24 % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return sprintf('%d:%02d %s', $hour12, $mins, $period);
    }

    private static function storedTimeToHm(mixed $value): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return '00:00';
    }

    private static function timeRangeDurationSeconds(string $start, string $end): int
    {
        $startMinutes = self::timeToMinutes($start);
        $endMinutes = self::timeToMinutes($end);

        if ($startMinutes === null || $endMinutes === null) {
            return 0;
        }

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return ($endMinutes - $startMinutes) * 60;
    }

    private static function timeToMinutes(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
