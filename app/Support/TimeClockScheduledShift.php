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

        return self::shiftsForDate($employee, $now->toDateString())->isEmpty()
            ? self::ISSUE_NO_SHIFT_TODAY
            : null;
    }

    public static function findShiftForClockIn(Employee $employee, ?CarbonInterface $now = null): ?EmployeeScheduleShift
    {
        $now = $now ?? DisplayTimezone::now();

        return self::shiftsForDate($employee, $now->toDateString())->first();
    }
}
