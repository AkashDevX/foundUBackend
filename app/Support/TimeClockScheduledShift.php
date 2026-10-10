<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Weekly schedule rules for mobile clock-in (employee_schedule_shifts).
 */
final class TimeClockScheduledShift
{
    public const ISSUE_NO_SHIFT_TODAY = 'no_scheduled_shift_today';

    /**
     * @return Collection<int, EmployeeScheduleShift>
     */
    public static function shiftsForDate(Employee $employee, string $date): Collection
    {
        return EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
            ->whereDate('scheduled_date', $date)
            ->orderBy('start_time')
            ->get();
    }

    public static function shiftIssue(Employee $employee, ?CarbonInterface $now = null): ?string
    {
        $now = $now ?? DisplayTimezone::now();

        return self::hasShiftForDate($employee, $now)
            ? null
            : self::ISSUE_NO_SHIFT_TODAY;
    }

    /**
     * A shift exists for the date if there's a concrete schedule row, or — when the day is not a
     * day off and no concrete row exists yet — the employee's assignment shift runs that weekday.
     * Keeps clock-in eligibility in
     * sync with what the schedule screen displays.
     */
    public static function hasShiftForDate(Employee $employee, CarbonInterface $now): bool
    {
        $date = $now->toDateString();

        if (self::shiftsForDate($employee, $date)->isNotEmpty()) {
            return true;
        }

        if (self::hasTimeOffForDate($employee, $date)) {
            return false;
        }

        if (InductionEligibility::blocksWork($employee)) {
            return false;
        }

        return AdminWeeklySchedule::hasAssignmentShiftForDate($employee, $now);
    }

    public static function findShiftForClockIn(Employee $employee, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        $now = $now ?? DisplayTimezone::now();
        $date = $now->toDateString();

        $existing = self::shiftsForDate($employee, $date);
        if ($existing->isNotEmpty()) {
            return self::clockInTargetForEmployee($employee, $now);
        }

        if (self::hasTimeOffForDate($employee, $date)) {
            return null;
        }

        if (InductionEligibility::blocksWork($employee)) {
            return null;
        }

        // No concrete row yet — materialize today's shift from the employee's assignment so the
        // punch links to a real schedule row (same shift the weekly schedule shows).
        if (AdminWeeklySchedule::materializeAssignmentShiftsForDate($employee, $now) > 0) {
            return self::pickBestForMoment(self::shiftsForDate($employee, $date), $now);
        }

        return null;
    }

    /**
     * Choose the schedule row that matches the current time slot when an employee has
     * multiple shifts on the same day.
     *
     * Preference order:
     * 1. Shift window that contains $now (including overnight windows)
     * 2. Nearest upcoming shift start
     * 3. Most recent past shift start
     *
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     */
    public static function pickBestForMoment(Collection $shifts, CarbonInterface $now): ?EmployeeScheduleShift
    {
        if ($shifts->isEmpty()) {
            return null;
        }

        $nowMinutes = ($now->hour * 60) + $now->minute;

        $scored = $shifts
            ->map(static function (EmployeeScheduleShift $shift) use ($nowMinutes): ?array {
                $start = self::storedTimeToMinutes($shift->start_time);
                $end = self::storedTimeToMinutes($shift->end_time);
                if ($start === null) {
                    return null;
                }

                return [
                    'shift' => $shift,
                    'start' => $start,
                    'end' => $end,
                    'contains' => self::windowContains($start, $end, $nowMinutes),
                ];
            })
            ->filter()
            ->values();

        if ($scored->isEmpty()) {
            return $shifts->first();
        }

        $containing = $scored
            ->filter(static fn (array $row): bool => $row['contains'])
            ->sortBy(static fn (array $row): int => abs($row['start'] - $nowMinutes))
            ->values();

        if ($containing->isNotEmpty()) {
            return $containing->first()['shift'];
        }

        $upcoming = $scored
            ->filter(static fn (array $row): bool => $row['start'] >= $nowMinutes)
            ->sortBy(static fn (array $row): int => $row['start'])
            ->values();

        if ($upcoming->isNotEmpty()) {
            return $upcoming->first()['shift'];
        }

        return $scored
            ->sortByDesc(static fn (array $row): int => $row['start'])
            ->first()['shift'];
    }

    /**
     * Shifts still to work today: every same-day row that does not yet have a clock-out.
     *
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @param  list<int>  $finishedIds
     * @return Collection<int, EmployeeScheduleShift>
     */
    public static function withoutFinishedIds(Collection $shifts, array $finishedIds): Collection
    {
        $finished = array_map(static fn (mixed $id): int => (int) $id, $finishedIds);

        return $shifts
            ->reject(static fn (EmployeeScheduleShift $shift): bool => in_array((int) $shift->id, $finished, true))
            ->values();
    }

    /**
     * Today's unfinished roster row with this id, if the employee still has it.
     */
    public static function unfinishedShiftById(Employee $employee, int $scheduleShiftId, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        if ($scheduleShiftId <= 0) {
            return null;
        }

        $localNow = ($now ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name());
        $unfinished = self::withoutFinished($employee, self::shiftsForDate($employee, $localNow->toDateString()));

        $match = $unfinished->first(
            static fn (EmployeeScheduleShift $shift): bool => (int) $shift->id === $scheduleShiftId,
        );

        return $match instanceof EmployeeScheduleShift ? $match : null;
    }

    /**
     * Which unfinished shift the employee may clock into.
     *
     * Preference order:
     * 1. A shift whose scheduled hours contain $now (earliest start if two overlap)
     * 2. A shift whose grace window contains $now
     * 3. The earliest shift whose window has not opened yet
     * 4. The latest remaining shift, once every shift has ended
     *
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     */
    public static function selectClockInTarget(Collection $shifts, CarbonInterface $now, int $graceMinutes): ?EmployeeScheduleShift
    {
        $graceMinutes = ClockInGrace::clampMinutes($graceMinutes);
        $nowMinutes = ($now->hour * 60) + $now->minute;

        $scored = $shifts
            ->map(static function (EmployeeScheduleShift $shift) use ($nowMinutes, $graceMinutes): ?array {
                $start = self::storedTimeToMinutes($shift->start_time);
                if ($start === null) {
                    return null;
                }
                $end = self::storedTimeToMinutes($shift->end_time);

                $earliest = $start - $graceMinutes;
                $latest = $start + $graceMinutes;
                $deviation = null;
                if ($nowMinutes < $earliest) {
                    $deviation = ClockInGrace::KIND_EARLY;
                } elseif ($nowMinutes > $latest) {
                    $deviation = ClockInGrace::KIND_LATE;
                }

                return [
                    'shift' => $shift,
                    'start' => $start,
                    'contains' => self::windowContains($start, $end, $nowMinutes),
                    'deviation' => $deviation,
                ];
            })
            ->filter()
            ->sortBy(static fn (array $row): int => $row['start'])
            ->values();

        if ($scored->isEmpty()) {
            return $shifts->first();
        }

        $inProgress = $scored
            ->filter(static fn (array $row): bool => $row['contains'] === true)
            ->values();
        if ($inProgress->isNotEmpty()) {
            return $inProgress->first()['shift'];
        }

        $open = $scored
            ->filter(static fn (array $row): bool => $row['deviation'] === null)
            ->values();
        if ($open->isNotEmpty()) {
            return $open->first()['shift'];
        }

        $upcoming = $scored
            ->filter(static fn (array $row): bool => $row['deviation'] === ClockInGrace::KIND_EARLY)
            ->values();
        if ($upcoming->isNotEmpty()) {
            return $upcoming->first()['shift'];
        }

        return $scored->last()['shift'];
    }

    public static function clockInTargetForEmployee(Employee $employee, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        $localNow = ($now ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name());
        $unfinished = self::withoutFinished($employee, self::shiftsForDate($employee, $localNow->toDateString()));
        if ($unfinished->isEmpty()) {
            return null;
        }

        return self::selectClockInTarget($unfinished, $localNow, self::graceMinutes($employee));
    }

    /**
     * Site and shift the mobile home should treat as current.
     * An open punch stays on the roster row it clocked into. Otherwise the next
     * unfinished shift (and that row's weekly-schedule work location) is used.
     */
    public static function shiftRowForLiveSite(Employee $employee, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        $openId = self::openSessionScheduleShiftId($employee);
        if ($openId !== null) {
            $open = EmployeeScheduleShift::query()->find($openId);
            if ($open instanceof EmployeeScheduleShift && (int) $open->employee_id === (int) $employee->id) {
                return $open;
            }
        }

        // Still clocked in, but this punch is not tied to a roster row.
        // Callers should keep the site stamped on the punch instead of jumping
        // to a later shift.
        if (self::openSessionClockIn($employee) instanceof TimeClockEntry) {
            return null;
        }

        return self::clockInTargetForEmployee($employee, $now);
    }

    public static function openSessionClockIn(Employee $employee): ?TimeClockEntry
    {
        if (! Schema::connection($employee->getConnectionName())->hasTable('time_clock_entries')) {
            return null;
        }

        $connection = $employee->getConnectionName();
        $last = TimeClockEntry::on($connection)
            ->where('employee_id', $employee->id)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->first();
        if (! $last instanceof TimeClockEntry || ! in_array($last->event_type, TimeClockEntry::ON_SHIFT_EVENTS, true)) {
            return null;
        }

        $clockIn = TimeClockEntry::on($connection)
            ->where('employee_id', $employee->id)
            ->where('event_type', TimeClockEntry::EVENT_CLOCK_IN)
            ->orderByDesc('clocked_at')
            ->orderByDesc('id')
            ->first();

        return $clockIn instanceof TimeClockEntry ? $clockIn : null;
    }

    /**
     * @return array{
     *     scheduled_shift: array{start_time: string, end_time: string, start_label: string, end_label: string}|null,
     *     scheduled_shifts: list<array<string, mixed>>
     * }
     */
    public static function homePayload(Employee $employee, ?CarbonInterface $now = null): array
    {
        $localNow = ($now ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name());
        $shifts = self::shiftsForDate($employee, $localNow->toDateString());
        $finishedIds = self::finishedScheduleShiftIds($employee, $shifts);
        $unfinished = self::withoutFinishedIds($shifts, $finishedIds);
        if ($shifts->isEmpty()) {
            return [
                'scheduled_shift' => self::todayShiftForDisplay($employee, $localNow),
                'scheduled_shifts' => [],
            ];
        }

        $shifts->load('workLocation');
        $grace = self::graceMinutes($employee);
        $target = $unfinished->isEmpty()
            ? null
            : self::selectClockInTarget($unfinished, $localNow, $grace);
        $clockedIn = self::openSessionClockIn($employee) instanceof TimeClockEntry;
        $openId = self::openSessionScheduleShiftId($employee);
        $currentId = $clockedIn
            ? $openId
            : ($target?->id !== null ? (int) $target->id : null);

        $rows = [];
        $currentTimes = null;
        foreach ($shifts as $shift) {
            $times = self::timeLabels($shift->start_time, $shift->end_time);
            $startMinutes = self::storedTimeToMinutes($shift->start_time);
            $withinWindow = false;
            if ($startMinutes !== null) {
                $nowMinutes = ($localNow->hour * 60) + $localNow->minute;
                $withinWindow = $nowMinutes >= ($startMinutes - $grace) && $nowMinutes <= ($startMinutes + $grace);
            }
            $isFinished = in_array((int) $shift->id, $finishedIds, true);
            $isCurrent = ! $isFinished && $currentId !== null && (int) $shift->id === $currentId;
            $location = $shift->workLocation instanceof WorkLocation ? $shift->workLocation : null;
            $row = [
                'id' => (int) $shift->id,
                'start_time' => $times['start_time'],
                'end_time' => $times['end_time'],
                'start_label' => $times['start_label'],
                'end_label' => $times['end_label'],
                'within_window' => $withinWindow,
                'is_current' => $isCurrent,
                'is_finished' => $isFinished,
                'work_location_name' => $location?->name,
                'work_location' => self::workLocationPayload($location),
            ];
            $rows[] = $row;
            if ($isCurrent) {
                $currentTimes = $times;
            }
        }

        return [
            'scheduled_shift' => $currentTimes,
            'scheduled_shifts' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function workLocationPayload(?WorkLocation $location): ?array
    {
        if (! $location instanceof WorkLocation) {
            return null;
        }

        return [
            'id' => $location->id,
            'name' => $location->name,
            'address' => $location->address,
            'latitude' => $location->latitude !== null ? (float) $location->latitude : null,
            'longitude' => $location->longitude !== null ? (float) $location->longitude : null,
            'geofence_radius_meters' => $location->resolvedGeofenceRadiusMeters(),
        ];
    }

    /**
     * Read-only shift times for today's schedule, for the mobile clock-in pill.
     *
     * @return array{start_time: string, end_time: string, start_label: string, end_label: string}|null
     */
    public static function todayShiftForDisplay(Employee $employee, ?CarbonInterface $now = null): ?array
    {
        $now = $now ?? DisplayTimezone::now();

        return AdminWeeklySchedule::shiftTimesForDate($employee, $now);
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @return Collection<int, EmployeeScheduleShift>
     */
    private static function withoutFinished(Employee $employee, Collection $shifts): Collection
    {
        return self::withoutFinishedIds($shifts, self::finishedScheduleShiftIds($employee, $shifts));
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @return list<int>
     */
    private static function finishedScheduleShiftIds(Employee $employee, Collection $shifts): array
    {
        if (! self::entriesHaveScheduleShiftId($employee)) {
            return [];
        }

        $ids = $shifts
            ->map(static fn (EmployeeScheduleShift $shift): int => (int) $shift->id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->values()
            ->all();
        if ($ids === []) {
            return [];
        }

        return TimeClockEntry::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->where('event_type', TimeClockEntry::EVENT_CLOCK_OUT)
            ->whereIn('schedule_shift_id', $ids)
            ->pluck('schedule_shift_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public static function openSessionScheduleShiftId(Employee $employee): ?int
    {
        if (! self::entriesHaveScheduleShiftId($employee)) {
            return null;
        }

        $id = self::openSessionClockIn($employee)?->schedule_shift_id;

        return $id !== null && (int) $id > 0 ? (int) $id : null;
    }

    private static function graceMinutes(Employee $employee): int
    {
        $settings = ClockInGraceSettings::current($employee->getConnectionName());

        return ClockInGrace::clampMinutes((int) ($settings['grace_minutes'] ?? 20));
    }

    private static function entriesHaveScheduleShiftId(Employee $employee): bool
    {
        $connection = $employee->getConnectionName();
        static $cache = [];
        if (array_key_exists($connection, $cache)) {
            return $cache[$connection];
        }

        return $cache[$connection] = Schema::connection($connection)->hasTable('time_clock_entries')
            && Schema::connection($connection)->hasColumn('time_clock_entries', 'schedule_shift_id');
    }

    /**
     * @return array{start_time: string, end_time: string, start_label: string, end_label: string}
     */
    private static function timeLabels(mixed $start, mixed $end): array
    {
        $startHm = ClockInGrace::storedTimeToHm($start);
        $endHm = ClockInGrace::storedTimeToHm($end);

        return [
            'start_time' => $startHm,
            'end_time' => $endHm,
            'start_label' => self::formatHmLabel($startHm),
            'end_label' => self::formatHmLabel($endHm),
        ];
    }

    private static function formatHmLabel(string $hm): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $hm, $matches)) {
            return $hm;
        }

        $hour = (int) $matches[1];
        $minute = $matches[2];
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return $hour12.':'.$minute.' '.$suffix;
    }

    private static function hasTimeOffForDate(Employee $employee, string $date): bool
    {
        return EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF)
            ->whereDate('scheduled_date', $date)
            ->exists();
    }

    private static function windowContains(int $startMinutes, ?int $endMinutes, int $nowMinutes): bool
    {
        if ($endMinutes === null) {
            return $nowMinutes === $startMinutes;
        }

        // Overnight: e.g. 22:00–06:00
        if ($endMinutes <= $startMinutes) {
            return $nowMinutes >= $startMinutes || $nowMinutes <= $endMinutes;
        }

        return $nowMinutes >= $startMinutes && $nowMinutes <= $endMinutes;
    }

    private static function storedTimeToMinutes(mixed $value): ?int
    {
        if ($value instanceof CarbonInterface) {
            return ($value->hour * 60) + $value->minute;
        }

        if (! is_string($value) || ! preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }
}
