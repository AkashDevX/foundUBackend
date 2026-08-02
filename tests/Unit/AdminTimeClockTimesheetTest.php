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
        // 8h05m worked vs 8h scheduled → +0.083h (≈5 minutes)
        $this->assertTrue($row['difference_is_alert']);
        $this->assertSame('0.083', $row['difference_hours']);
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

    public function test_build_groups_shows_multiple_breaks_and_total_duration(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-break',
            'full_legal_name' => 'Break Tester',
            'email' => 'break@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 9;

        $clockIn = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        ]);
        $clockIn->id = 91;

        $break1Start = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 10:00:00', 'UTC'),
        ]);
        $break1Start->id = 92;

        $break1End = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 10:15:00', 'UTC'),
        ]);
        $break1End->id = 93;

        $break2Start = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 12:00:00', 'UTC'),
        ]);
        $break2Start->id = 94;

        $break2End = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 12:30:00', 'UTC'),
        ]);
        $break2End->id = 95;

        $clockOut = new TimeClockEntry([
            'employee_id' => 9,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 17:00:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);
        $clockOut->id = 96;

        $employee->setRelation('timeClockEntries', new Collection([
            $clockIn,
            $break1Start,
            $break1End,
            $break2Start,
            $break2End,
            $clockOut,
        ]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection(),
            new Collection(),
            null
        );

        $row = $result['groups'][0]['rows'][0];
        $this->assertSame("10:00 AM\n12:00 PM", $row['break_start']);
        $this->assertSame("10:15 AM\n12:30 PM", $row['break_end']);
        // 15m + 30m = 0.75 hours
        $this->assertSame('0.75', $row['break_duration_hours']);
        // 8h wall - 0.75h break = 7.25h worked (no paid allowance without shift template)
        $this->assertSame('7.25', $row['worked_duration_hours']);
        $this->assertSame('0.75', $result['groups'][0]['summary']['break_duration_hours']);
        $this->assertIsArray($row['worked_duration_breakdown']);
        $this->assertNotEmpty($row['worked_duration_breakdown']['lines']);
        $this->assertCount(2, $row['break_items']);
        $this->assertSame('Break 1', $row['break_items'][0]['label']);
        $this->assertSame('10:00 AM', $row['break_items'][0]['start']);
        $this->assertSame('10:15 AM', $row['break_items'][0]['end']);
        $this->assertSame('0.25', $row['break_items'][0]['duration_hours']);
        $this->assertSame('Break 2', $row['break_items'][1]['label']);
        $this->assertSame('12:00 PM', $row['break_items'][1]['start']);
        $this->assertSame('12:30 PM', $row['break_items'][1]['end']);
        $this->assertSame('0.50', $row['break_items'][1]['duration_hours']);
        $this->assertSame('2 breaks', $row['break_type']);
    }

    public function test_build_groups_keeps_paid_break_in_shift_duration(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-paid-break',
            'full_legal_name' => 'Paid Break Tester',
            'email' => 'paid-break@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 11;

        $shift = new \App\Models\Shift([
            'name' => 'Day',
            'breaks' => [
                ['label' => 'Tea', 'minutes' => 15, 'paid' => true],
                ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
            ],
        ]);
        $shift->id = 7;

        $scheduleShift = new EmployeeScheduleShift([
            'employee_id' => 11,
            'scheduled_date' => '2026-06-29',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'shift_id' => 7,
        ]);
        $scheduleShift->id = 70;
        $scheduleShift->setRelation('shiftTemplate', $shift);

        $clockIn = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
            'shift_id' => 7,
        ]);
        $clockIn->id = 110;
        $clockIn->setRelation('shift', $shift);

        $break1Start = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 10:00:00', 'UTC'),
        ]);
        $break1Start->id = 111;
        $break1End = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 10:15:00', 'UTC'),
        ]);
        $break1End->id = 112;

        $break2Start = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 12:00:00', 'UTC'),
        ]);
        $break2Start->id = 113;
        $break2End = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 12:45:00', 'UTC'),
        ]);
        $break2End->id = 114;

        $clockOut = new TimeClockEntry([
            'employee_id' => 11,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 17:00:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);
        $clockOut->id = 115;

        $employee->setRelation('timeClockEntries', new Collection([
            $clockIn,
            $break1Start,
            $break1End,
            $break2Start,
            $break2End,
            $clockOut,
        ]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$scheduleShift]),
            new Collection(),
            null
        );

        $row = $result['groups'][0]['rows'][0];
        // 8h wall, 60m breaks taken; keep 15m paid, deduct 30m unpaid + 15m excess = 7.25h
        // Scheduled payable = 8h − 30m unpaid allocation = 7.50h
        $this->assertSame('1.00', $row['break_duration_hours']);
        $this->assertSame('7.25', $row['worked_duration_hours']);
        $this->assertSame('7.50', $row['scheduled_duration_hours']);
        $this->assertSame('-0.250', $row['difference_hours']);
        $labels = array_column($row['worked_duration_breakdown']['lines'], 'label');
        $this->assertContains('Paid break (kept)', $labels);
        $this->assertContains('− Unpaid break', $labels);
        $this->assertContains('− Excess (unpaid)', $labels);
    }

    public function test_one_minute_paid_break_deducts_one_minute_excess(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-1m-paid',
            'full_legal_name' => 'One Minute Paid',
            'email' => 'one-min@example.com',
            'employment_type' => 'casual',
        ]);
        $employee->id = 13;

        $shift = new \App\Models\Shift([
            'name' => 'Short',
            'breaks' => [
                ['label' => 'Paid break', 'minutes' => 1, 'paid' => true],
            ],
        ]);
        $shift->id = 9;

        $scheduleShift = new EmployeeScheduleShift([
            'employee_id' => 13,
            'scheduled_date' => '2026-06-29',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '03:00',
            'end_time' => '09:00',
            'shift_id' => 9,
        ]);
        $scheduleShift->id = 90;
        $scheduleShift->setRelation('shiftTemplate', $shift);

        // 3 minutes on site, two 1-minute breaks → 1m paid kept, 1m excess unpaid
        $clockIn = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 03:38:00', 'UTC'),
            'shift_id' => 9,
        ]);
        $clockIn->id = 130;
        $clockIn->setRelation('shift', $shift);

        $break1Start = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 03:39:00', 'UTC'),
        ]);
        $break1Start->id = 131;
        $break1End = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 03:40:00', 'UTC'),
        ]);
        $break1End->id = 132;

        $break2Start = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 03:40:00', 'UTC'),
        ]);
        $break2Start->id = 133;
        $break2End = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 03:41:00', 'UTC'),
        ]);
        $break2End->id = 134;

        $clockOut = new TimeClockEntry([
            'employee_id' => 13,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 03:41:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);
        $clockOut->id = 135;

        $employee->setRelation('timeClockEntries', new Collection([
            $clockIn,
            $break1Start,
            $break1End,
            $break2Start,
            $break2End,
            $clockOut,
        ]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$scheduleShift]),
            new Collection(),
            null
        );

        $row = $result['groups'][0]['rows'][0];
        // 3m wall − 1m excess = 2m payable (0.033h), not 0.05h
        $this->assertSame('0.033', $row['worked_duration_hours']);
        $lastLine = $row['worked_duration_breakdown']['lines'][array_key_last($row['worked_duration_breakdown']['lines'])];
        $this->assertSame('2m (0.033h)', $lastLine['value']);
        $this->assertContains('− Excess (unpaid)', array_column($row['worked_duration_breakdown']['lines'], 'label'));
    }

    public function test_exact_paid_and_unpaid_breaks_match_scheduled_payable_duration(): void
    {
        config(['app.display_timezone' => 'UTC']);
        $weekStart = Carbon::parse('2026-06-29', 'UTC')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-exact-break',
            'full_legal_name' => 'Exact Break Tester',
            'email' => 'exact-break@example.com',
            'employment_type' => 'full_time',
        ]);
        $employee->id = 12;

        $shift = new \App\Models\Shift([
            'name' => 'Day',
            'breaks' => [
                ['label' => 'Tea', 'minutes' => 15, 'paid' => true],
                ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
            ],
        ]);
        $shift->id = 8;

        $scheduleShift = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-06-29',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
            'shift_id' => 8,
        ]);
        $scheduleShift->id = 80;
        $scheduleShift->setRelation('shiftTemplate', $shift);

        $clockIn = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
            'shift_id' => 8,
        ]);
        $clockIn->id = 120;
        $clockIn->setRelation('shift', $shift);

        $break1Start = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 10:00:00', 'UTC'),
        ]);
        $break1Start->id = 121;
        $break1End = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 10:15:00', 'UTC'),
        ]);
        $break1End->id = 122;

        $break2Start = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_BREAK_START,
            'clocked_at' => Carbon::parse('2026-06-29 12:00:00', 'UTC'),
        ]);
        $break2Start->id = 123;
        $break2End = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_BREAK_END,
            'clocked_at' => Carbon::parse('2026-06-29 12:30:00', 'UTC'),
        ]);
        $break2End->id = 124;

        $clockOut = new TimeClockEntry([
            'employee_id' => 12,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 17:00:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);
        $clockOut->id = 125;

        $employee->setRelation('timeClockEntries', new Collection([
            $clockIn,
            $break1Start,
            $break1End,
            $break2Start,
            $break2End,
            $clockOut,
        ]));

        $result = AdminTimeClockTimesheet::buildGroups(
            new Collection([$employee]),
            $weekStart,
            new Collection([$scheduleShift]),
            new Collection(),
            null
        );

        $row = $result['groups'][0]['rows'][0];
        // Perfect attendance: 8h wall, 45m breaks (15 paid + 30 unpaid) → both sides 7.50h
        $this->assertSame('7.50', $row['worked_duration_hours']);
        $this->assertSame('7.50', $row['scheduled_duration_hours']);
        $this->assertSame('0.00', $row['difference_hours']);
        $this->assertFalse($row['difference_is_alert']);
    }

    public function test_format_decimal_hours(): void
    {
        $this->assertSame('7.50', AdminTimeClockTimesheet::formatDecimalHours(7 * 3600 + 30 * 60));
        $this->assertSame('0.00', AdminTimeClockTimesheet::formatDecimalHours(0));
        $this->assertSame('-0.500', AdminTimeClockTimesheet::formatSignedDecimalHours(-30 * 60));
        $this->assertSame('0.250', AdminTimeClockTimesheet::formatSignedDecimalHours(15 * 60));
        $this->assertSame('0.033', AdminTimeClockTimesheet::formatTimesheetHours(2 * 60));
        $this->assertSame('2m', AdminTimeClockTimesheet::formatMinutesSeconds(120));
        $this->assertSame('1m 20s', AdminTimeClockTimesheet::formatMinutesSeconds(80));
    }
}
