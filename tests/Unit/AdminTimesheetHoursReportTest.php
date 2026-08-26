<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use App\Support\AdminTimesheetHoursReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminTimesheetHoursReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'UTC']);
    }

    public function test_build_lists_each_shift_with_date_start_finish_and_hours(): void
    {
        $employee = $this->employee(1, 'John Smith');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 06:00:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 10:00:00'),
            $this->entry(3, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-13 06:00:00'),
            $this->entry(4, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-13 10:00:00'),
        ]));

        $result = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-13', 'UTC')->startOfDay(),
            new Collection(),
            null
        );

        $this->assertCount(2, $result['shifts']);
        $this->assertSame('John Smith', $result['shifts'][0]['employee']);
        $this->assertSame('12/08/2026', $result['shifts'][0]['date_label']);
        $this->assertSame('6:00 AM', $result['shifts'][0]['start_time']);
        $this->assertSame('10:00 AM', $result['shifts'][0]['finish_time']);
        $this->assertSame(4.0, $result['shifts'][0]['hours']);
        $this->assertSame('13/08/2026', $result['shifts'][1]['date_label']);
        $this->assertSame(4.0, $result['shifts'][1]['hours']);

        $this->assertCount(1, $result['summaries']);
        $this->assertSame('John Smith', $result['summaries'][0]['employee']);
        $this->assertSame(2, $result['summaries'][0]['days']);
        $this->assertSame(2, $result['summaries'][0]['sessions']);
        $this->assertSame(8.0, $result['summaries'][0]['hours']);
        $this->assertSame(8.0, $result['stats']['hours']);
        $this->assertSame(2, $result['stats']['shifts']);
        $this->assertSame(2, $result['stats']['days']);
    }

    public function test_build_keeps_two_shifts_on_the_same_day_as_separate_rows(): void
    {
        $employee = $this->employee(1, 'John Smith');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 06:00:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 10:00:00'),
            $this->entry(3, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 14:00:00'),
            $this->entry(4, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 18:00:00'),
        ]));

        $result = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            new Collection(),
            null
        );

        $this->assertCount(2, $result['shifts']);
        $this->assertSame('6:00 AM', $result['shifts'][0]['start_time']);
        $this->assertSame('2:00 PM', $result['shifts'][1]['start_time']);
        $this->assertSame(1, $result['summaries'][0]['days']);
        $this->assertSame(2, $result['summaries'][0]['sessions']);
        $this->assertSame(8.0, $result['summaries'][0]['hours']);
    }

    public function test_build_filters_by_approval_status(): void
    {
        $employee = $this->employee(4, 'Alex');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(21, 4, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 08:00:00'),
            $this->entry(22, 4, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 12:00:00'),
            $this->entry(23, 4, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-13 08:00:00'),
            $this->entry(24, 4, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-13 12:00:00'),
        ]));

        $approval = new TimesheetApproval([
            'employee_id' => 4,
            'clock_in_entry_id' => 21,
            'work_date' => '2026-08-12',
            'total_seconds' => 4 * 3600,
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);

        $approved = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-13', 'UTC')->startOfDay(),
            new Collection([$approval]),
            'approved'
        );
        $pending = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-13', 'UTC')->startOfDay(),
            new Collection([$approval]),
            'pending'
        );

        $this->assertCount(1, $approved['shifts']);
        $this->assertSame('12/08/2026', $approved['shifts'][0]['date_label']);
        $this->assertSame(TimesheetApproval::STATUS_APPROVED, $approved['shifts'][0]['status']);
        $this->assertCount(1, $pending['shifts']);
        $this->assertSame('13/08/2026', $pending['shifts'][0]['date_label']);
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $pending['shifts'][0]['status']);
    }

    public function test_build_excludes_shifts_outside_the_selected_period(): void
    {
        $employee = $this->employee(1, 'Alex Worker');
        $employee->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-11 09:00:00'),
            $this->entry(2, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-11 17:00:00'),
            $this->entry(3, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:00:00'),
            $this->entry(4, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 17:00:00'),
        ]));

        $result = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            new Collection(),
            null
        );

        $this->assertCount(1, $result['shifts']);
        $this->assertSame('12/08/2026', $result['shifts'][0]['date_label']);
    }

    public function test_open_shift_shows_in_progress_finish_time(): void
    {
        Carbon::setTestNow('2026-08-12 08:00:00');

        $employee = $this->employee(1, 'Open Shift');
        $clockIn = $this->entry(10, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 06:00:00');
        $employee->setRelation('timeClockEntries', new Collection([$clockIn]));

        $result = AdminTimesheetHoursReport::build(
            new Collection([$employee]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            new Collection(),
            null
        );

        $this->assertCount(1, $result['shifts']);
        $this->assertSame('6:00 AM', $result['shifts'][0]['start_time']);
        $this->assertSame('In progress', $result['shifts'][0]['finish_time']);
        $this->assertSame(2.0, $result['shifts'][0]['hours']);
        $this->assertTrue($result['shifts'][0]['is_open']);

        Carbon::setTestNow();
    }

    public function test_summaries_are_sorted_by_employee_name(): void
    {
        $zoe = $this->employee(2, 'Zoe Adams');
        $zoe->setRelation('timeClockEntries', new Collection([
            $this->entry(1, 2, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:00:00'),
            $this->entry(2, 2, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 10:00:00'),
        ]));

        $alex = $this->employee(1, 'Alex Brown');
        $alex->setRelation('timeClockEntries', new Collection([
            $this->entry(3, 1, TimeClockEntry::EVENT_CLOCK_IN, '2026-08-12 09:00:00'),
            $this->entry(4, 1, TimeClockEntry::EVENT_CLOCK_OUT, '2026-08-12 11:00:00'),
        ]));

        $result = AdminTimesheetHoursReport::build(
            new Collection([$zoe, $alex]),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            Carbon::parse('2026-08-12', 'UTC')->startOfDay(),
            new Collection(),
            null
        );

        $this->assertSame(['Alex Brown', 'Zoe Adams'], array_column($result['shifts'], 'employee'));
        $this->assertSame(['Alex Brown', 'Zoe Adams'], array_column($result['summaries'], 'employee'));
    }

    private function employee(int $id, string $name): Employee
    {
        $employee = new Employee([
            'public_id' => 'emp-'.$id,
            'full_legal_name' => $name,
            'email' => 'emp'.$id.'@example.com',
        ]);
        $employee->id = $id;

        return $employee;
    }

    private function entry(int $id, int $employeeId, string $eventType, string $clockedAt): TimeClockEntry
    {
        $entry = new TimeClockEntry([
            'employee_id' => $employeeId,
            'event_type' => $eventType,
            'clocked_at' => Carbon::parse($clockedAt, 'UTC'),
        ]);
        $entry->id = $id;

        return $entry;
    }
}
