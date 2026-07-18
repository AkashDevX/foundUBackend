<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use App\Support\AdminTimeClockTimesheet;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminTimeClockTimesheetTest extends TestCase
{
    public function test_build_groups_pairs_schedule_with_clock_punches(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Aimee Fromm',
            'email' => 'aimee@example.com',
            'employment_type' => 'part_time',
        ]);
        $employee->id = 1;

        $scheduleShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-06-29',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:30',
            'end_time' => '17:30',
        ]);
        $scheduleShift->id = 10;

        $clockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 09:29:00', 'UTC'),
            'distance_from_site_meters' => 42.5,
            'device_latitude' => -27.47,
            'device_longitude' => 153.02,
            'expected_latitude' => -27.4698,
            'expected_longitude' => 153.0251,
            'allowed_radius_meters' => 100,
            'within_geofence' => true,
        ]);
        $clockIn->id = 1;

        $clockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 17:34:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
            'comment' => 'Left early for appointment',
        ]);
        $clockOut->id = 2;

        $employee->setRelation('timeClockEntries', new Collection([$clockIn, $clockOut]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$scheduleShift]),
            new Collection(),
            null
        );

        $this->assertCount(1, $result['groups']);
        $group = $result['groups'][0];
        $this->assertSame('Aimee Fromm', $group['name']);
        $this->assertCount(1, $group['rows']);

        $row = $group['rows'][0];
        $this->assertSame('Part-time', $row['employment_type']);
        $this->assertSame('9:29 AM', $row['clock_in']);
        $this->assertSame('43', $row['clock_in_distance_meters']);
        $this->assertSame('5:34 PM', $row['clock_out']);
        $this->assertSame('8.00', $row['scheduled_duration_hours']);
        $this->assertSame('No', $row['auto_clock_out']);
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $row['status']);
        $this->assertSame('2026-06-29', $row['work_date']);
        $this->assertTrue($row['difference_is_alert']);
        $this->assertTrue($row['can_review']);
        $this->assertFalse($row['can_reset']);
        $this->assertIsArray($row['clock_in_map']);
        $this->assertTrue($row['clock_in_map']['within_geofence']);
        $this->assertSame(-27.47, $row['clock_in_map']['device_latitude']);
        $this->assertSame('Left early for appointment', $row['modal']['clock_out_comment']);
    }

    public function test_approved_row_can_reset_to_pending(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee(['email' => 'c@example.com', 'employment_type' => 'casual']);
        $employee->id = 3;

        $clockIn = new TimeClockEntry([
            'employee_id' => 3,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 08:00:00', 'UTC'),
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 3,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        ]);

        $clockIn->id = 5;
        $clockOut->id = 6;

        $employee->setRelation('timeClockEntries', new Collection([$clockIn, $clockOut]));

        $approval = new TimesheetApproval([
            'employee_id' => 3,
            'clock_in_entry_id' => 5,
            'work_date' => '2026-06-29',
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection([$approval]),
            'approved'
        );

        $row = $result['groups'][0]['rows'][0];
        $this->assertFalse($row['can_review']);
        $this->assertTrue($row['can_reset']);
    }

    public function test_build_groups_filters_by_daily_status(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee(['email' => 'b@example.com']);
        $employee->id = 2;

        $clockIn = new TimeClockEntry([
            'employee_id' => 2,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 08:00:00', 'UTC'),
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 2,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        ]);

        $clockIn->id = 3;
        $clockOut->id = 4;

        $employee->setRelation('timeClockEntries', new Collection([$clockIn, $clockOut]));

        $approval = new TimesheetApproval([
            'employee_id' => 2,
            'clock_in_entry_id' => 3,
            'work_date' => '2026-06-29',
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);

        $pending = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection([$approval]),
            'pending'
        );
        $approved = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection([$approval]),
            'approved'
        );

        $this->assertSame([], $pending['groups']);
        $this->assertCount(1, $approved['groups']);
        $this->assertSame(TimesheetApproval::STATUS_APPROVED, $approved['groups'][0]['rows'][0]['status']);
    }

    public function test_build_week_index_returns_recent_weeks(): void
    {
        config(['app.display_timezone' => 'UTC']);
        Carbon::setTestNow('2026-07-07 12:00:00');

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex',
            'email' => 'alex@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 1;

        $clockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-30 09:00:00', 'UTC'),
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-30 17:00:00', 'UTC'),
        ]);
        $employee->setRelation('timeClockEntries', new Collection([$clockIn, $clockOut]));

        $weeks = AdminTimeClockTimesheet::buildWeekIndex(
            new Collection([$employee]),
            new Collection(),
            new Collection(),
            3
        );

        $this->assertCount(3, $weeks);
        $this->assertTrue($weeks[0]['is_current']);
        $this->assertSame(1, $weeks[1]['stats']['employees']);

        Carbon::setTestNow();
    }

    public function test_build_groups_only_includes_selected_week_schedule_shifts(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Worker',
            'email' => 'alex@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 1;
        $employee->setRelation('timeClockEntries', new Collection());

        $currentWeekShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-06-30',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $currentWeekShift->id = 10;

        $otherWeekShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-07-07',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $otherWeekShift->id = 11;

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$currentWeekShift, $otherWeekShift]),
            new Collection(),
            null
        );

        $this->assertSame([], $result['groups']);
    }

    public function test_build_groups_excludes_scheduled_shift_without_clock_punches(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Worker',
            'email' => 'alex@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 1;
        $employee->setRelation('timeClockEntries', new Collection());

        $scheduledShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-06-30',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $scheduledShift->id = 10;

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$scheduledShift]),
            new Collection(),
            null
        );

        $this->assertSame([], $result['groups']);
    }

    public function test_build_groups_only_includes_selected_week_clock_entries(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Worker',
            'email' => 'alex@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 1;

        $currentWeekClockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-30 09:00:00', 'UTC'),
        ]);
        $currentWeekClockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-30 17:00:00', 'UTC'),
        ]);

        $otherWeekClockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-07-07 09:00:00', 'UTC'),
        ]);
        $otherWeekClockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-07-07 17:00:00', 'UTC'),
        ]);

        $employee->setRelation('timeClockEntries', new Collection([
            $currentWeekClockIn,
            $currentWeekClockOut,
            $otherWeekClockIn,
            $otherWeekClockOut,
        ]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection(),
            null
        );

        $this->assertCount(1, $result['groups']);
        $this->assertCount(1, $result['groups'][0]['rows']);
        $this->assertSame('2026-06-30', $result['groups'][0]['rows'][0]['work_date']);
    }

    public function test_build_groups_reuses_day_session_for_additional_schedule_rows(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Worker',
            'email' => 'alex@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 1;

        $clockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-30 09:00:00', 'UTC'),
            'distance_from_site_meters' => 55,
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-30 17:00:00', 'UTC'),
            'distance_from_site_meters' => 60,
        ]);
        $employee->setRelation('timeClockEntries', new Collection([$clockIn, $clockOut]));

        $morningShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-06-30',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);
        $morningShift->id = 10;

        $afternoonShift = new EmployeeScheduleShift([
            'employee_id' => 1,
            'scheduled_date' => '2026-06-30',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '13:00',
            'end_time' => '17:00',
        ]);
        $afternoonShift->id = 11;

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$morningShift, $afternoonShift]),
            new Collection(),
            null
        );

        $this->assertCount(1, $result['groups']);
        $this->assertCount(2, $result['groups'][0]['rows']);
        $this->assertSame('55', $result['groups'][0]['rows'][0]['clock_in_distance_meters']);
        $this->assertSame('55', $result['groups'][0]['rows'][1]['clock_in_distance_meters']);
    }

    public function test_build_groups_resolves_status_per_session_on_same_day(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee(['email' => 'multi@example.com', 'employment_type' => 'full_time']);
        $employee->id = 4;

        $morningIn = new TimeClockEntry([
            'employee_id' => 4,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-30 08:00:00', 'UTC'),
        ]);
        $morningOut = new TimeClockEntry([
            'employee_id' => 4,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-30 12:00:00', 'UTC'),
        ]);
        $afternoonIn = new TimeClockEntry([
            'employee_id' => 4,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-30 13:00:00', 'UTC'),
        ]);
        $afternoonOut = new TimeClockEntry([
            'employee_id' => 4,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-30 17:00:00', 'UTC'),
        ]);
        $morningIn->id = 21;
        $morningOut->id = 22;
        $afternoonIn->id = 23;
        $afternoonOut->id = 24;

        $employee->setRelation('timeClockEntries', new Collection([
            $morningIn,
            $morningOut,
            $afternoonIn,
            $afternoonOut,
        ]));

        $approval = new TimesheetApproval([
            'employee_id' => 4,
            'clock_in_entry_id' => 21,
            'work_date' => '2026-06-30',
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection([$approval]),
            null
        );

        $rows = $result['groups'][0]['rows'];
        $this->assertCount(2, $rows);
        $this->assertSame(TimesheetApproval::STATUS_APPROVED, $rows[0]['status']);
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $rows[1]['status']);
    }

    public function test_format_decimal_hours(): void
    {
        $this->assertSame('7.50', AdminTimeClockTimesheet::formatDecimalHours(7 * 3600 + 30 * 60));
        $this->assertSame('0.00', AdminTimeClockTimesheet::formatDecimalHours(0));
    }
}
