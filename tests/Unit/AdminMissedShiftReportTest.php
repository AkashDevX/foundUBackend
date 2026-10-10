<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Support\AdminMissedShiftReport;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminMissedShiftReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.display_timezone' => 'UTC']);
    }

    public function test_lists_active_employees_who_did_not_clock_in_for_an_allocated_shift(): void
    {
        $missed = $this->employee(1, 'Mina NoShow', 'active', 'E-1');
        $arrived = $this->employee(2, 'Alex Arrived', 'active', 'E-2');
        $inactive = $this->employee(3, 'Ian Inactive', 'inactive', 'E-3');

        $result = AdminMissedShiftReport::build(
            new Collection([$missed, $arrived, $inactive]),
            new Collection([
                $this->shift(10, 1, '2026-10-02', '09:00', '17:00', 'Dock', 'Operations'),
                $this->shift(11, 2, '2026-10-02', '09:00', '17:00'),
                $this->shift(12, 3, '2026-10-02', '09:00', '17:00'),
            ]),
            new Collection([
                $this->clockIn(2, '2026-10-02 09:02:00', 11),
            ]),
            new Collection,
            new Collection,
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-10 12:00:00', 'UTC'),
        );

        $this->assertSame(1, $result['stats']['missed_employees']);
        $this->assertSame(1, $result['stats']['missed_shifts']);
        $this->assertSame(2, $result['stats']['rostered_employees']);
        $this->assertSame(2, $result['stats']['allocated_shifts']);
        $this->assertSame(1, $result['stats']['attended_shifts']);
        $this->assertSame('Mina NoShow', $result['rows'][0]['employee']);
        $this->assertSame('E-1', $result['rows'][0]['employee_code']);
        $this->assertSame('02 Oct 2026', $result['rows'][0]['date_label']);
        $this->assertSame('9:00 AM – 5:00 PM', $result['rows'][0]['time_label']);
        $this->assertSame('Dock', $result['rows'][0]['location']);
        $this->assertSame('Operations', $result['rows'][0]['department']);
        $this->assertSame('2 Oct', $result['summaries'][0]['dates']);
        $this->assertSame(1, $result['summaries'][0]['missed']);
    }

    public function test_unlinked_clock_in_on_the_shift_date_counts_as_arrived(): void
    {
        $employee = $this->employee(1, 'Sam', 'active');

        $result = AdminMissedShiftReport::build(
            new Collection([$employee]),
            new Collection([$this->shift(20, 1, '2026-10-03', '08:00', '16:00')]),
            new Collection([$this->clockIn(1, '2026-10-03 08:10:00', null)]),
            new Collection,
            new Collection,
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-10 12:00:00', 'UTC'),
        );

        $this->assertSame(0, $result['stats']['missed_shifts']);
        $this->assertSame(1, $result['stats']['attended_shifts']);
    }

    public function test_clock_in_linked_to_another_shift_does_not_clear_a_second_shift(): void
    {
        $employee = $this->employee(1, 'Sam', 'active');

        $result = AdminMissedShiftReport::build(
            new Collection([$employee]),
            new Collection([
                $this->shift(21, 1, '2026-10-04', '06:00', '10:00'),
                $this->shift(22, 1, '2026-10-04', '14:00', '18:00'),
            ]),
            new Collection([$this->clockIn(1, '2026-10-04 06:01:00', 21)]),
            new Collection,
            new Collection,
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-10 12:00:00', 'UTC'),
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('2:00 PM – 6:00 PM', $result['rows'][0]['time_label']);
        $this->assertSame(2, $result['summaries'][0]['allocated']);
        $this->assertSame(1, $result['summaries'][0]['missed']);
    }

    public function test_ignores_leave_time_off_sick_call_out_and_covered_shifts(): void
    {
        $original = $this->employee(1, 'Original', 'active');
        $cover = $this->employee(2, 'Cover', 'active');
        $onLeave = $this->employee(3, 'On Leave', 'active');
        $dayOff = $this->employee(4, 'Day Off', 'active');
        $sick = $this->employee(5, 'Sick', 'active');

        $coveredOriginal = $this->shift(30, 1, '2026-10-05', '09:00', '17:00');
        $coveredOriginal->cover_status = EmployeeScheduleShift::COVER_ASSIGNED;

        $covering = $this->shift(31, 2, '2026-10-05', '09:00', '17:00');

        $leaveShift = $this->shift(32, 3, '2026-10-06', '09:00', '17:00');
        $timeOffShift = $this->shift(33, 4, '2026-10-07', '09:00', '17:00');
        $sickShift = $this->shift(34, 5, '2026-10-08', '09:00', '17:00');
        $sickShift->status = EmployeeScheduleShift::STATUS_SICK_CALL_OUT;

        $timeOff = new EmployeeScheduleShift([
            'employee_id' => 4,
            'scheduled_date' => '2026-10-07',
            'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF,
        ]);

        $leave = new EmployeeLeaveRecord([
            'employee_id' => 3,
            'leave_date' => '2026-10-06',
            'status' => EmployeeLeaveRecord::STATUS_RECORDED,
        ]);

        $result = AdminMissedShiftReport::build(
            new Collection([$original, $cover, $onLeave, $dayOff, $sick]),
            new Collection([$coveredOriginal, $covering, $leaveShift, $timeOffShift, $sickShift]),
            new Collection,
            new Collection([$timeOff]),
            new Collection([$leave]),
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-10 12:00:00', 'UTC'),
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('Cover', $result['rows'][0]['employee']);
        $this->assertSame('05 Oct 2026', $result['rows'][0]['date_label']);
    }

    public function test_skips_shifts_that_have_not_finished_and_other_months(): void
    {
        $employee = $this->employee(1, 'Sam', 'active');

        $result = AdminMissedShiftReport::build(
            new Collection([$employee]),
            new Collection([
                $this->shift(40, 1, '2026-09-30', '09:00', '17:00'),
                $this->shift(41, 1, '2026-10-10', '09:00', '17:00'),
                $this->shift(42, 1, '2026-10-02', '22:00', '06:00'),
                $this->shift(43, 1, '2026-10-01', '09:00', '17:00'),
            ]),
            new Collection,
            new Collection,
            new Collection,
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-03 05:00:00', 'UTC'),
        );

        $this->assertCount(1, $result['rows']);
        $this->assertSame('01 Oct 2026', $result['rows'][0]['date_label']);
        $this->assertSame('9:00 AM – 5:00 PM', $result['rows'][0]['time_label']);

        $afterOvernight = AdminMissedShiftReport::build(
            new Collection([$employee]),
            new Collection([
                $this->shift(42, 1, '2026-10-02', '22:00', '06:00'),
            ]),
            new Collection,
            new Collection,
            new Collection,
            Carbon::parse('2026-10-01', 'UTC'),
            Carbon::parse('2026-10-03 06:00:00', 'UTC'),
        );

        $this->assertSame('02 Oct 2026', $afterOvernight['rows'][0]['date_label']);
        $this->assertSame('10:00 PM – 6:00 AM', $afterOvernight['rows'][0]['time_label']);
    }

    private function employee(int $id, string $name, string $status, string $code = ''): Employee
    {
        $employee = new Employee([
            'full_legal_name' => $name,
            'employment_status' => $status,
            'employee_code' => $code,
        ]);
        $employee->id = $id;

        return $employee;
    }

    private function shift(
        int $id,
        int $employeeId,
        string $date,
        string $start,
        string $end,
        ?string $location = null,
        ?string $department = null,
    ): EmployeeScheduleShift {
        $shift = new EmployeeScheduleShift([
            'employee_id' => $employeeId,
            'scheduled_date' => $date,
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $shift->id = $id;

        if ($location !== null) {
            $site = new WorkLocation(['name' => $location]);
            $shift->setRelation('workLocation', $site);
        }
        if ($department !== null) {
            $dept = new Department(['name' => $department]);
            $shift->setRelation('department', $dept);
        }

        return $shift;
    }

    private function clockIn(int $employeeId, string $at, ?int $scheduleShiftId): TimeClockEntry
    {
        return new TimeClockEntry([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => $at,
            'schedule_shift_id' => $scheduleShiftId,
        ]);
    }
}
