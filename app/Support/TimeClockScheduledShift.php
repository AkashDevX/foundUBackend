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
     * day off and no concrete row exists yet — the employee's assignment shift runs that weekday
     * (the same shift the weekly schedule shows as a suggestion). Keeps clock-in eligibility in
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

        $existing = self::shiftsForDate($employee, $date)->first();
        if ($existing instanceof EmployeeScheduleShift) {
            return $existing;
        }

        if (self::hasTimeOffForDate($employee, $date)) {
            return null;
        }

        // No concrete row yet — materialize today's shift from the employee's assignment so the
        // punch links to a real schedule row (same shift the weekly schedule shows).
        if (AdminWeeklySchedule::materializeAssignmentShiftsForDate($employee, $now) > 0) {
            return self::shiftsForDate($employee, $date)->first();
        }

        return null;
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
}
