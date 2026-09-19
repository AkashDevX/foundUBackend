<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

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

        return AdminWeeklySchedule::hasAssignmentShiftForDate($employee, $now);
    }

    public static function findShiftForClockIn(Employee $employee, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        $now = $now ?? DisplayTimezone::now();
        $date = $now->toDateString();

        $existing = self::shiftsForDate($employee, $date);
        if ($existing->isNotEmpty()) {
            return self::pickBestForMoment($existing, $now);
        }

        if (self::hasTimeOffForDate($employee, $date)) {
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
     * Read-only shift times for today's schedule, for the mobile clock-in pill.
     *
     * @return array{start_time: string, end_time: string, start_label: string, end_label: string}|null
     */
    public static function todayShiftForDisplay(Employee $employee, ?CarbonInterface $now = null): ?array
    {
        $now = $now ?? DisplayTimezone::now();

        return AdminWeeklySchedule::shiftTimesForDate($employee, $now);
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
