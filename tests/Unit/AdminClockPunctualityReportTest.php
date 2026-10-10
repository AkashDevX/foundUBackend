<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Support\AdminClockPunctualityReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminClockPunctualityReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'UTC']);
    }

    public function test_flags_early_and_late_punches_against_the_allocated_shift(): void
    {
        $employee = $this->employee(1, 'John Smith');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:45:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:20:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([$this->shift(10, 1, '2026-08-12', '09:00', '17:00')]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $this->assertCount(1, $result['rows']);
        $row = $result['rows'][0];
        $this->assertSame('John Smith', $row['employee']);
        $this->assertSame('12/08/2026', $row['date_label']);
        $this->assertSame('9:00 AM – 5:00 PM', $row['allocated']);
        $this->assertSame('8:45 AM', $row['clock_in']);
        $this->assertSame('15 min early', $row['clock_in_variance']);
        $this->assertSame('5:20 PM', $row['clock_out']);
        $this->assertSame('20 min late', $row['clock_out_variance']);
        $this->assertTrue($row['early_in']);
        $this->assertTrue($row['late_out']);
        $this->assertFalse($row['late_in']);
        $this->assertFalse($row['early_out']);
        $this->assertSame(1, $result['stats']['early_in']);
        $this->assertSame(1, $result['stats']['late_out']);
        $this->assertSame(1, $result['stats']['employees']);
        $this->assertSame(1, $result['summaries'][0]['early_in']);
        $this->assertSame(1, $result['summaries'][0]['late_out']);
    }

    public function test_flags_late_clock_in_and_early_clock_out(): void
    {
        $employee = $this->employee(2, 'Alex Brown');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(3, 2, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:12:00'),
            $this->entry(4, 2, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 16:40:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([$this->shift(11, 2, '2026-08-12', '09:00', '17:00')]),
            Carbon::parse('2026-08-01', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-31', 'UTC')->startOfDay(),
        );

        $row = $result['rows'][0];
        $this->assertSame('12 min late', $row['clock_in_variance']);
        $this->assertSame('20 min early', $row['clock_out_variance']);
        $this->assertTrue($row['late_in']);
        $this->assertTrue($row['early_out']);
        $this->assertSame(1, $result['stats']['late_in']);
        $this->assertSame(1, $result['stats']['early_out']);
    }

    public function test_on_time_punches_and_unallocated_clock_records_are_left_out(): void
    {
        $onTime = $this->employee(1, 'On Time');
        $onTime->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:00:30'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:00:20'),
        ]));

        $unallocated = $this->employee(2, 'No Shift');
        $unallocated->setRelation('timeClockEntries', new Collection([
            $this->entry(3, 2, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:00:00'),
            $this->entry(4, 2, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 12:00:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$onTime, $unallocated]),
            new Collection([$this->shift(10, 1, '2026-08-12', '09:00', '17:00')]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $this->assertSame([], $result['rows']);
        $this->assertSame(0, $result['stats']['shifts']);
    }

    public function test_matches_each_punch_to_the_closest_shift_on_that_day(): void
    {
        $employee = $this->employee(1, 'Split Shift');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:20:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 12:00:00'),
            $this->entry(3, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 12:50:00'),
            $this->entry(4, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:10:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([
                $this->shift(21, 1, '2026-08-12', '08:00', '12:00'),
                $this->shift(22, 1, '2026-08-12', '13:00', '17:00'),
            ]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $this->assertCount(2, $result['rows']);
        $this->assertSame('8:00 AM – 12:00 PM', $result['rows'][0]['allocated']);
        $this->assertSame('20 min late', $result['rows'][0]['clock_in_variance']);
        $this->assertSame('On time', $result['rows'][0]['clock_out_variance']);
        $this->assertSame('1:00 PM – 5:00 PM', $result['rows'][1]['allocated']);
        $this->assertSame('10 min early', $result['rows'][1]['clock_in_variance']);
        $this->assertSame('10 min late', $result['rows'][1]['clock_out_variance']);
    }

    public function test_recorded_schedule_shift_wins_over_a_closer_start_time(): void
    {
        $employee = $this->employee(1, 'Linked');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:05:00', 22),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 13:00:00', 22),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([
                $this->shift(21, 1, '2026-08-12', '08:00', '12:00'),
                $this->shift(22, 1, '2026-08-12', '13:00', '17:00'),
            ]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('1:00 PM – 5:00 PM', $result['rows'][0]['allocated']);
        $this->assertSame('4h 55m early', $result['rows'][0]['clock_in_variance']);
        $this->assertSame('4h early', $result['rows'][0]['clock_out_variance']);
    }

    public function test_overnight_shift_compares_clock_out_with_the_next_morning(): void
    {
        $employee = $this->employee(1, 'Night');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 21:50:00', 30),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-13 06:15:00', 30),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([$this->shift(30, 1, '2026-08-12', '22:00', '06:00')]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $row = $result['rows'][0];
        $this->assertSame('10:00 PM – 6:00 AM', $row['allocated']);
        $this->assertSame('10 min early', $row['clock_in_variance']);
        $this->assertSame('15 min late', $row['clock_out_variance']);
        $this->assertSame('12/08/2026', $row['date_label']);
    }

    public function test_open_shift_reports_the_clock_in_and_leaves_clock_out_blank(): void
    {
        $employee = $this->employee(1, 'Still In');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:10:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$employee]),
            new Collection([$this->shift(10, 1, '2026-08-12', '09:00', '17:00')]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
        );

        $row = $result['rows'][0];
        $this->assertSame('10 min late', $row['clock_in_variance']);
        $this->assertSame('In progress', $row['clock_out']);
        $this->assertSame('—', $row['clock_out_variance']);
        $this->assertFalse($row['early_out']);
        $this->assertFalse($row['late_out']);
        $this->assertSame(1, $result['stats']['late_in']);
        $this->assertSame(0, $result['stats']['early_out']);
    }

    public function test_variance_filter_keeps_only_the_selected_kind(): void
    {
        $early = $this->employee(1, 'Early In');
        $early->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:40:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:00:00'),
        ]));

        $late = $this->employee(2, 'Late In');
        $late->setRelation('timeClockEntries', new Collection([
            $this->entry(3, 2, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:25:00'),
            $this->entry(4, 2, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:00:00'),
        ]));

        $result = AdminClockPunctualityReport::build(
            new Collection([$late, $early]),
            new Collection([
                $this->shift(10, 1, '2026-08-12', '09:00', '17:00'),
                $this->shift(11, 2, '2026-08-12', '09:00', '17:00'),
            ]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            AdminClockPunctualityReport::EARLY_IN,
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Early In', $result['rows'][0]['employee']);
        $this->assertSame(1, $result['stats']['early_in']);
        $this->assertSame(0, $result['stats']['late_in']);
    }

    private function employee(int $id, string $name): Employee
    {
        $employee = new Employee([
            'public_id' => 'emp-'.$id,
            'full_legal_name' => $name,
        ]);
        $employee->id = $id;

        return $employee;
    }

    private function entry(int $id, int $employeeId, string $eventType, string $clockedAt, ?int $scheduleShiftId = null): TimeClockEntry
    {
        $entry = new TimeClockEntry([
            'employee_id' => $employeeId,
            'event_type' => $eventType,
            'clocked_at' => Carbon::parse($clockedAt, 'UTC'),
            'schedule_shift_id' => $scheduleShiftId,
        ]);
        $entry->id = $id;

        return $entry;
    }

    private function shift(int $id, int $employeeId, string $date, string $start, string $end): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'employee_id' => $employeeId,
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'scheduled_date' => $date,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $shift->id = $id;

        return $shift;
    }
}
