<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Compare clocked punches with the allocated shift start and finish.
 *
 * A punch is early when it is before the rostered time and late when it is
 * after it. The same minute as the roster counts as on time. Shifts with no
 * clock record, and punches with no allocated shift, are left out.
 */
final class AdminClockPunctualityReport
{
    public const EARLY_IN = 'early_in';

    public const LATE_IN = 'late_in';

    public const EARLY_OUT = 'early_out';

    public const LATE_OUT = 'late_out';

    public const KIND_EARLY = 'early';

    public const KIND_LATE = 'late';

    public const KIND_ON_TIME = 'on_time';

    /** @var list<string> */
    public const FILTERS = [
        self::EARLY_IN,
        self::LATE_IN,
        self::EARLY_OUT,
        self::LATE_OUT,
    ];

    /**
     * @param  Collection<int, Employee>  $employees  Employees with timeClockEntries loaded.
     * @param  Collection<int, EmployeeScheduleShift>  $scheduleShifts
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summaries: list<array<string, mixed>>,
     *     stats: array{early_in: int, late_in: int, early_out: int, late_out: int, employees: int, shifts: int},
     * }
     */
    public static function build(
        Collection $employees,
        Collection $scheduleShifts,
        CarbonInterface $from,
        CarbonInterface $to,
        ?string $varianceFilter = null,
    ): array {
        $tz = DisplayTimezone::name();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $varianceFilter = in_array($varianceFilter, self::FILTERS, true) ? $varianceFilter : null;

        $shiftsByEmployee = $scheduleShifts
            ->filter(static fn (EmployeeScheduleShift $shift): bool => $shift->entry_type === EmployeeScheduleShift::TYPE_SHIFT)
            ->groupBy(static fn (EmployeeScheduleShift $shift): int => (int) $shift->employee_id);

        $rows = [];

        foreach ($employees as $employee) {
            $employeeId = (int) $employee->id;
            $entries = $employee->timeClockEntries ?? collect();
            if ($entries->isEmpty()) {
                continue;
            }

            $sessions = self::sessions($entries, $tz);
            $employeeShifts = $shiftsByEmployee->get($employeeId, collect());
            $pairs = self::pairSessionsToShifts($sessions, $employeeShifts, $tz);

            foreach ($pairs as $pair) {
                /** @var EmployeeScheduleShift $shift */
                $shift = $pair['shift'];
                $workDate = $shift->scheduled_date?->toDateString() ?? '';
                if ($workDate === '' || $workDate < $fromDate || $workDate > $toDate) {
                    continue;
                }

                $row = self::buildRow($employee, $shift, $pair['session'], $workDate, $tz);
                if ($row === null) {
                    continue;
                }

                if ($varianceFilter !== null && ! ($row[$varianceFilter] ?? false)) {
                    continue;
                }

                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
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

        $summaries = self::summarizeByEmployee($rows);

        return [
            'rows' => $rows,
            'summaries' => $summaries,
            'stats' => [
                'early_in' => self::countFlag($rows, self::EARLY_IN),
                'late_in' => self::countFlag($rows, self::LATE_IN),
                'early_out' => self::countFlag($rows, self::EARLY_OUT),
                'late_out' => self::countFlag($rows, self::LATE_OUT),
                'employees' => count($summaries),
                'shifts' => count($rows),
            ],
        ];
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @return list<array{clock_in: TimeClockEntry, clock_out: TimeClockEntry|null, is_open: bool, date: string}>
     */
    private static function sessions(Collection $entries, string $tz): array
    {
        $sorted = $entries
            ->sortBy(static fn (TimeClockEntry $entry): array => [
                $entry->clocked_at?->getTimestamp() ?? 0,
                (int) $entry->id,
            ])
            ->values();

        $sessions = [];
        $openClockIn = null;

        foreach ($sorted as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                if ($openClockIn instanceof TimeClockEntry) {
                    $sessions[] = self::session($openClockIn, null, $tz);
                }
                $openClockIn = $entry;

                continue;
            }

            if ($entry->event_type !== TimeClockEntry::EVENT_CLOCK_OUT || ! $openClockIn instanceof TimeClockEntry) {
                continue;
            }

            $sessions[] = self::session($openClockIn, $entry, $tz);
            $openClockIn = null;
        }

        if ($openClockIn instanceof TimeClockEntry) {
            $sessions[] = self::session($openClockIn, null, $tz);
        }

        return $sessions;
    }

    /**
     * @return array{clock_in: TimeClockEntry, clock_out: TimeClockEntry|null, is_open: bool, date: string}
     */
    private static function session(TimeClockEntry $clockIn, ?TimeClockEntry $clockOut, string $tz): array
    {
        return [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'is_open' => ! $clockOut instanceof TimeClockEntry,
            'date' => $clockIn->clocked_at?->copy()->timezone($tz)->toDateString() ?? '',
        ];
    }

    /**
     * Prefer the shift recorded on the clock-in. Otherwise bind each session to
     * the closest allocated start on the same day, one session per shift.
     *
     * @param  list<array{clock_in: TimeClockEntry, clock_out: TimeClockEntry|null, is_open: bool, date: string}>  $sessions
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @return list<array{session: array<string, mixed>, shift: EmployeeScheduleShift}>
     */
    private static function pairSessionsToShifts(array $sessions, Collection $shifts, string $tz): array
    {
        $shifts = $shifts
            ->filter(static fn (EmployeeScheduleShift $shift): bool => $shift->scheduled_date !== null)
            ->values();

        $byId = $shifts->keyBy(static fn (EmployeeScheduleShift $shift): int => (int) $shift->id);
        $usedShiftIds = [];
        $unmatched = [];
        $pairs = [];

        foreach ($sessions as $index => $session) {
            $clockIn = $session['clock_in'];
            $shiftId = (int) ($clockIn->schedule_shift_id ?? 0);
            $linked = $shiftId > 0 ? $byId->get($shiftId) : null;

            if ($linked instanceof EmployeeScheduleShift && ! isset($usedShiftIds[$shiftId])) {
                $pairs[] = ['session' => $session, 'shift' => $linked];
                $usedShiftIds[$shiftId] = true;

                continue;
            }

            $unmatched[$index] = $session;
        }

        $remaining = $shifts
            ->filter(static fn (EmployeeScheduleShift $shift): bool => ! isset($usedShiftIds[(int) $shift->id]))
            ->values();

        foreach (self::assignClosest($unmatched, $remaining, $tz) as $sessionIndex => $shift) {
            $pairs[] = ['session' => $unmatched[$sessionIndex], 'shift' => $shift];
        }

        return $pairs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $sessions
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @return array<int, EmployeeScheduleShift> sessionIndex => shift
     */
    private static function assignClosest(array $sessions, Collection $shifts, string $tz): array
    {
        $candidates = [];

        foreach ($shifts as $shift) {
            $scheduleDate = $shift->scheduled_date?->toDateString();
            if ($scheduleDate === null) {
                continue;
            }

            $startMinutes = self::minutesOfDay(ClockInGrace::storedTimeToHm($shift->start_time));

            foreach ($sessions as $sessionIndex => $session) {
                if (($session['date'] ?? '') !== $scheduleDate) {
                    continue;
                }

                $clockIn = $session['clock_in'] ?? null;
                if (! $clockIn instanceof TimeClockEntry || $clockIn->clocked_at === null) {
                    continue;
                }

                $clockLocal = $clockIn->clocked_at->copy()->timezone($tz);
                $clockMinutes = ((int) $clockLocal->format('G') * 60) + (int) $clockLocal->format('i');
                $candidates[] = [
                    'session_index' => (int) $sessionIndex,
                    'shift' => $shift,
                    'distance' => abs($clockMinutes - $startMinutes),
                    'shift_id' => (int) $shift->id,
                ];
            }
        }

        usort($candidates, static function (array $a, array $b): int {
            $distanceCmp = $a['distance'] <=> $b['distance'];
            if ($distanceCmp !== 0) {
                return $distanceCmp;
            }

            return $a['shift_id'] <=> $b['shift_id'];
        });

        $assignments = [];
        $usedSessions = [];
        $usedShifts = [];

        foreach ($candidates as $candidate) {
            $sessionIndex = $candidate['session_index'];
            $shiftId = $candidate['shift_id'];
            if (isset($usedSessions[$sessionIndex]) || isset($usedShifts[$shiftId])) {
                continue;
            }

            $assignments[$sessionIndex] = $candidate['shift'];
            $usedSessions[$sessionIndex] = true;
            $usedShifts[$shiftId] = true;
        }

        return $assignments;
    }

    /**
     * @param  array{clock_in: TimeClockEntry, clock_out: TimeClockEntry|null, is_open: bool, date: string}  $session
     * @return array<string, mixed>|null
     */
    private static function buildRow(
        Employee $employee,
        EmployeeScheduleShift $shift,
        array $session,
        string $workDate,
        string $tz,
    ): ?array {
        $clockIn = $session['clock_in'];
        if (! $clockIn instanceof TimeClockEntry || $clockIn->clocked_at === null) {
            return null;
        }

        $shiftStart = ClockInGrace::shiftStart($shift);
        $shiftEnd = EarlyClockOutGate::shiftEnd($shift, $shiftStart);
        $inMinutes = self::signedMinutes($clockIn->clocked_at, $shiftStart);
        $inKind = self::kind($inMinutes);

        $clockOut = $session['clock_out'] ?? null;
        $outMinutes = null;
        $outKind = null;
        $clockOutLabel = 'In progress';
        $clockOutVariance = '—';
        $clockOutClass = 'text-brand-text-secondary';

        if ($clockOut instanceof TimeClockEntry && $clockOut->clocked_at !== null) {
            $outMinutes = self::signedMinutes($clockOut->clocked_at, $shiftEnd);
            $outKind = self::kind($outMinutes);
            $clockOutLabel = DisplayTimezone::format($clockOut->clocked_at, 'g:i A');
            $clockOutVariance = self::formatVariance($outMinutes);
            $clockOutClass = self::varianceClass($outKind);
        }

        $earlyIn = $inKind === self::KIND_EARLY;
        $lateIn = $inKind === self::KIND_LATE;
        $earlyOut = $outKind === self::KIND_EARLY;
        $lateOut = $outKind === self::KIND_LATE;

        if (! $earlyIn && ! $lateIn && ! $earlyOut && ! $lateOut) {
            return null;
        }

        $name = self::employeeName($employee);

        return [
            'employee' => $name,
            'employee_id' => (int) $employee->id,
            'work_date' => $workDate,
            'date_label' => Carbon::parse($workDate, $tz)->format('d/m/Y'),
            'allocated' => $shiftStart->format('g:i A').' – '.$shiftEnd->format('g:i A'),
            'clock_in' => DisplayTimezone::format($clockIn->clocked_at, 'g:i A'),
            'clock_in_variance' => self::formatVariance($inMinutes),
            'clock_in_class' => self::varianceClass($inKind),
            'clock_out' => $clockOutLabel,
            'clock_out_variance' => $clockOutVariance,
            'clock_out_class' => $clockOutClass,
            self::EARLY_IN => $earlyIn,
            self::LATE_IN => $lateIn,
            self::EARLY_OUT => $earlyOut,
            self::LATE_OUT => $lateOut,
            'sort_name' => mb_strtolower($name),
            'sort_time' => $shiftStart->format('H:i'),
        ];
    }

    private static function signedMinutes(CarbonInterface $actual, CarbonInterface $scheduled): int
    {
        $actualTs = $actual->copy()->timezone($scheduled->timezone)->seconds(0)->utc()->getTimestamp();
        $scheduledTs = $scheduled->copy()->seconds(0)->utc()->getTimestamp();

        return (int) (($actualTs - $scheduledTs) / 60);
    }

    private static function kind(int $minutes): string
    {
        if ($minutes < 0) {
            return self::KIND_EARLY;
        }

        if ($minutes > 0) {
            return self::KIND_LATE;
        }

        return self::KIND_ON_TIME;
    }

    public static function formatVariance(int $minutes): string
    {
        if ($minutes === 0) {
            return 'On time';
        }

        $direction = $minutes < 0 ? 'early' : 'late';
        $abs = abs($minutes);
        if ($abs < 60) {
            return $abs.' min '.$direction;
        }

        $hours = intdiv($abs, 60);
        $mins = $abs % 60;
        $text = $mins > 0 ? $hours.'h '.$mins.'m' : $hours.'h';

        return $text.' '.$direction;
    }

    private static function varianceClass(string $kind): string
    {
        return match ($kind) {
            self::KIND_EARLY => 'font-semibold text-amber-700',
            self::KIND_LATE => 'font-semibold text-red-600',
            default => 'text-brand-text-secondary',
        };
    }

    private static function minutesOfDay(string $hm): int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $hm, $matches)) {
            return 0;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private static function countFlag(array $rows, string $flag): int
    {
        return count(array_filter($rows, static fn (array $row): bool => (bool) ($row[$flag] ?? false)));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{employee: string, early_in: int, late_in: int, early_out: int, late_out: int}>
     */
    private static function summarizeByEmployee(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $id = (int) $row['employee_id'];
            if (! isset($grouped[$id])) {
                $grouped[$id] = [
                    'employee' => (string) $row['employee'],
                    'sort_name' => (string) $row['sort_name'],
                    'early_in' => 0,
                    'late_in' => 0,
                    'early_out' => 0,
                    'late_out' => 0,
                ];
            }

            foreach (self::FILTERS as $flag) {
                if ($row[$flag] ?? false) {
                    $grouped[$id][$flag]++;
                }
            }
        }

        $summaries = array_values($grouped);
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
