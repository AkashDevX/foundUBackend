<?php

namespace App\Support;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Active employees who were allocated a shift and did not clock in.
 * One calendar month at a time. Leave, time off, sick call outs, and
 * shifts covered by someone else are not treated as a missed arrival.
 */
final class AdminMissedShiftReport
{
    /**
     * @param  Collection<int, Employee>  $employees
     * @param  Collection<int, EmployeeScheduleShift>  $shifts
     * @param  Collection<int, TimeClockEntry>  $clockIns
     * @param  Collection<int, EmployeeScheduleShift>  $timeOff
     * @param  Collection<int, EmployeeLeaveRecord>  $leave
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     summaries: list<array<string, mixed>>,
     *     stats: array{rostered_employees: int, missed_employees: int, allocated_shifts: int, missed_shifts: int, attended_shifts: int}
     * }
     */
    public static function build(
        Collection $employees,
        Collection $shifts,
        Collection $clockIns,
        Collection $timeOff,
        Collection $leave,
        CarbonInterface $month,
        CarbonInterface $now,
    ): array {
        $tz = DisplayTimezone::name();
        $now = $now->copy()->timezone($tz);
        $monthStart = $month->copy()->timezone($tz)->startOfMonth()->toDateString();
        $monthEnd = $month->copy()->timezone($tz)->endOfMonth()->toDateString();

        $active = $employees
            ->filter(static fn (Employee $employee): bool => ($employee->employment_status ?? '') === 'active')
            ->keyBy(static fn (Employee $employee): int => (int) $employee->id);

        $excused = self::excusedDates($timeOff, $leave);
        $punchesByEmployee = $clockIns
            ->filter(static fn (TimeClockEntry $entry): bool => $entry->event_type === TimeClockEntry::EVENT_CLOCK_IN && $entry->clocked_at !== null)
            ->groupBy(static fn (TimeClockEntry $entry): int => (int) $entry->employee_id);

        $allocatedByEmployee = [];
        $missedByEmployee = [];
        $rows = [];

        foreach ($shifts as $shift) {
            if (! $shift instanceof EmployeeScheduleShift) {
                continue;
            }

            $date = $shift->scheduled_date?->toDateString();
            $employeeId = (int) $shift->employee_id;
            $employee = $active->get($employeeId);
            if ($date === null || ! $employee instanceof Employee) {
                continue;
            }
            if ($date < $monthStart || $date > $monthEnd) {
                continue;
            }
            if (! self::expectsAttendance($shift)) {
                continue;
            }
            if (isset($excused[$employeeId.'|'.$date])) {
                continue;
            }
            if (! self::hasEnded($shift, $now, $tz)) {
                continue;
            }

            $allocatedByEmployee[$employeeId] = ($allocatedByEmployee[$employeeId] ?? 0) + 1;

            $punches = $punchesByEmployee->get($employeeId) ?? new Collection;
            if (self::arrived($shift, $punches, $date, $tz)) {
                continue;
            }

            $missedByEmployee[$employeeId] ??= [];
            $missedByEmployee[$employeeId][] = $date;

            $rows[] = [
                'employee_id' => $employeeId,
                'employee' => self::employeeName($employee),
                'employee_code' => trim((string) ($employee->employee_code ?? '')),
                'date' => $date,
                'date_label' => Carbon::parse($date, $tz)->format('d M Y'),
                'time_label' => self::timeLabel($shift),
                'location' => self::relatedName($shift, 'workLocation'),
                'department' => self::relatedName($shift, 'department'),
                'sort_name' => mb_strtolower(self::employeeName($employee)),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $byName = strcmp($a['sort_name'], $b['sort_name']);
            if ($byName !== 0) {
                return $byName;
            }

            return strcmp($a['date'], $b['date']) ?: strcmp($a['time_label'], $b['time_label']);
        });

        $summaries = [];
        foreach ($missedByEmployee as $employeeId => $dates) {
            $employee = $active->get((int) $employeeId);
            if (! $employee instanceof Employee) {
                continue;
            }
            $uniqueDates = array_values(array_unique($dates));
            sort($uniqueDates);
            $summaries[] = [
                'employee_id' => (int) $employeeId,
                'employee' => self::employeeName($employee),
                'employee_code' => trim((string) ($employee->employee_code ?? '')),
                'allocated' => (int) ($allocatedByEmployee[$employeeId] ?? count($dates)),
                'missed' => count($dates),
                'dates' => implode(', ', array_map(
                    static fn (string $date): string => Carbon::parse($date, $tz)->format('j M'),
                    $uniqueDates,
                )),
                'sort_name' => mb_strtolower(self::employeeName($employee)),
            ];
        }

        usort($summaries, static function (array $a, array $b): int {
            $byMissed = $b['missed'] <=> $a['missed'];
            if ($byMissed !== 0) {
                return $byMissed;
            }

            return strcmp($a['sort_name'], $b['sort_name']);
        });

        $missedShifts = count($rows);
        $allocatedShifts = array_sum($allocatedByEmployee);

        return [
            'rows' => array_map(static function (array $row): array {
                unset($row['sort_name']);

                return $row;
            }, $rows),
            'summaries' => array_map(static function (array $row): array {
                unset($row['sort_name']);

                return $row;
            }, $summaries),
            'stats' => [
                'rostered_employees' => count($allocatedByEmployee),
                'missed_employees' => count($summaries),
                'allocated_shifts' => $allocatedShifts,
                'missed_shifts' => $missedShifts,
                'attended_shifts' => $allocatedShifts - $missedShifts,
            ],
        ];
    }

    private static function expectsAttendance(EmployeeScheduleShift $shift): bool
    {
        if ($shift->entry_type !== EmployeeScheduleShift::TYPE_SHIFT) {
            return false;
        }

        if ($shift->status === EmployeeScheduleShift::STATUS_SICK_CALL_OUT) {
            return false;
        }

        return ! in_array($shift->cover_status, [
            EmployeeScheduleShift::COVER_ASSIGNED,
            EmployeeScheduleShift::COVER_LEAVE_UNCOVERED,
            EmployeeScheduleShift::COVER_UNASSIGNED,
            EmployeeScheduleShift::COVER_AVAILABLE,
        ], true);
    }

    /**
     * @param  Collection<int, EmployeeScheduleShift>  $timeOff
     * @param  Collection<int, EmployeeLeaveRecord>  $leave
     * @return array<string, true>
     */
    private static function excusedDates(Collection $timeOff, Collection $leave): array
    {
        $keys = [];

        foreach ($timeOff as $entry) {
            if (! $entry instanceof EmployeeScheduleShift || $entry->entry_type !== EmployeeScheduleShift::TYPE_TIME_OFF) {
                continue;
            }
            $date = $entry->scheduled_date?->toDateString();
            if ($date !== null) {
                $keys[(int) $entry->employee_id.'|'.$date] = true;
            }
        }

        foreach ($leave as $record) {
            if (! $record instanceof EmployeeLeaveRecord) {
                continue;
            }
            if ($record->status === EmployeeLeaveRecord::STATUS_CANCELLED) {
                continue;
            }
            $date = $record->leave_date?->toDateString();
            if ($date !== null) {
                $keys[(int) $record->employee_id.'|'.$date] = true;
            }
        }

        return $keys;
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $punches
     */
    private static function arrived(EmployeeScheduleShift $shift, Collection $punches, string $date, string $tz): bool
    {
        $shiftId = (int) $shift->id;

        if ($shiftId > 0 && $punches->contains(
            static fn (TimeClockEntry $entry): bool => (int) ($entry->schedule_shift_id ?? 0) === $shiftId
        )) {
            return true;
        }

        $onDate = $punches->filter(static function (TimeClockEntry $entry) use ($date, $tz): bool {
            return $entry->clocked_at?->copy()->timezone($tz)->toDateString() === $date;
        });

        if ($onDate->isEmpty()) {
            return false;
        }

        $linkedToAShift = $onDate->contains(
            static fn (TimeClockEntry $entry): bool => (int) ($entry->schedule_shift_id ?? 0) > 0
        );

        return ! $linkedToAShift;
    }

    private static function hasEnded(EmployeeScheduleShift $shift, CarbonInterface $now, string $tz): bool
    {
        return $now->greaterThanOrEqualTo(self::shiftEnd($shift, $tz));
    }

    private static function shiftEnd(EmployeeScheduleShift $shift, string $tz): CarbonInterface
    {
        $date = $shift->scheduled_date?->toDateString() ?? Carbon::now($tz)->toDateString();
        $start = Carbon::parse($date.' '.self::storedTime($shift->start_time), $tz);
        $end = Carbon::parse($date.' '.self::storedTime($shift->end_time, '17:00'), $tz);
        if ($end->lte($start)) {
            $end->addDay();
        }

        return $end;
    }

    private static function storedTime(mixed $value, string $default = '09:00'): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $default;
    }

    private static function timeLabel(EmployeeScheduleShift $shift): string
    {
        return self::clockLabel(self::storedTime($shift->start_time)).' – '.self::clockLabel(self::storedTime($shift->end_time, '17:00'));
    }

    private static function clockLabel(string $hm): string
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $hm, $matches)) {
            return $hm;
        }

        $hour = (int) $matches[1];
        $suffix = $hour >= 12 ? 'PM' : 'AM';
        $hour12 = $hour % 12;
        if ($hour12 === 0) {
            $hour12 = 12;
        }

        return $hour12.':'.$matches[2].' '.$suffix;
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

    private static function relatedName(EmployeeScheduleShift $shift, string $relation): string
    {
        if (! $shift->relationLoaded($relation)) {
            return '—';
        }

        $related = $shift->getRelation($relation);
        if (! $related instanceof Department && ! $related instanceof WorkLocation) {
            return '—';
        }

        $name = trim((string) ($related->name ?? ''));

        return $name !== '' ? $name : '—';
    }
}
