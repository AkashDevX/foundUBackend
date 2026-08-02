<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class PayrollRosterSummary
{
    /**
     * @return array{
     *     scheduled_hours: float,
     *     shift_count: int,
     *     time_off_count: int,
     *     roster_label: string,
     * }
     */
    public static function forEmployee(
        Employee $employee,
        string $fortnightStart,
        string $fortnightEnd,
    ): array {
        $tz = DisplayTimezone::name();
        $rangeStart = Carbon::parse($fortnightStart, $tz)->startOfDay();
        $rangeEnd = Carbon::parse($fortnightEnd, $tz)->endOfDay();

        /** @var Collection<int, EmployeeScheduleShift> $shifts */
        $shifts = $employee->scheduleShifts ?? collect();

        $scheduledSeconds = 0;
        $shiftCount = 0;
        $timeOffCount = 0;

        foreach ($shifts as $row) {
            if ($row->scheduled_date === null) {
                continue;
            }

            $date = $row->scheduled_date->copy()->timezone($tz)->startOfDay();
            if ($date->lt($rangeStart) || $date->gt($rangeEnd)) {
                continue;
            }

            if ($row->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF) {
                $timeOffCount++;

                continue;
            }

            if ($row->start_time === null || $row->end_time === null) {
                continue;
            }

            $startHm = $row->start_time instanceof \Carbon\CarbonInterface
                ? $row->start_time->format('H:i')
                : (is_string($row->start_time) ? substr($row->start_time, 0, 5) : '09:00');
            $endHm = $row->end_time instanceof \Carbon\CarbonInterface
                ? $row->end_time->format('H:i')
                : (is_string($row->end_time) ? substr($row->end_time, 0, 5) : '17:00');

            $start = Carbon::parse($row->scheduled_date->toDateString().' '.$startHm, $tz);
            $end = Carbon::parse($row->scheduled_date->toDateString().' '.$endHm, $tz);
            if ($end->lte($start)) {
                $end = $end->copy()->addDay();
            }

            $scheduledSeconds += max(0, $start->diffInSeconds($end));
            $shiftCount++;
        }

        $scheduledHours = round($scheduledSeconds / 3600, 2);

        return [
            'scheduled_hours' => $scheduledHours,
            'shift_count' => $shiftCount,
            'time_off_count' => $timeOffCount,
            'roster_label' => $shiftCount > 0
                ? sprintf('%s rostered shift(s), %.2f hrs', $shiftCount, $scheduledHours)
                : 'No rostered shifts in fortnight',
        ];
    }

    public static function varianceLabel(float $workedHours, float $scheduledHours): string
    {
        if ($scheduledHours <= 0) {
            return '—';
        }

        $delta = round($workedHours - $scheduledHours, 2);
        if (abs($delta) < 0.01) {
            return 'Matches roster';
        }

        return ($delta > 0 ? '+' : '').number_format($delta, 2).' hrs vs roster';
    }
}
