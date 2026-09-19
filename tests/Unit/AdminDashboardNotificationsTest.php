<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Models\TimeOffRequest;
use App\Support\AdminDashboardNotifications;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use ReflectionMethod;
use Tests\TestCase;

class AdminDashboardNotificationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-10 10:00:00', DisplayTimezone::name()));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_document_expiry_detects_expired_visa(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-visa',
            'full_legal_name' => 'Visa Holder',
            'email' => 'visa@example.com',
            'visa_expiry' => '2026-06-01',
        ]);
        $employee->id = 1;

        $items = $this->invokePrivate('documentExpiryItems', [
            new Collection([$employee]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            true,
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('expired', strtolower($items[0]['message']));
        $this->assertSame('urgent', $items[0]['severity']);
    }

    public function test_clocked_in_uses_latest_entry_not_limited_window(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-clock',
            'full_legal_name' => 'On Site',
            'email' => 'onsite@example.com',
            'employment_status' => 'active',
        ]);
        $employee->id = 5;

        $oldClockIn = new TimeClockEntry([
            'employee_id' => 5,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-01 08:00:00', DisplayTimezone::name()),
        ]);
        $oldClockIn->id = 1;

        $items = $this->invokePrivate('clockedInItems', [
            new Collection([$employee]),
            new Collection([$oldClockIn]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('clocked in since', $items[0]['message']);
        $this->assertStringContainsString('/admin/employees/time-clock', $items[0]['url']);
        $this->assertStringContainsString('employee=emp-clock', $items[0]['url']);
    }

    public function test_missing_clock_in_skips_excused_absence(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-off',
            'full_legal_name' => 'Off Today',
            'email' => 'off@example.com',
        ]);
        $employee->id = 7;

        $shift = new EmployeeScheduleShift([
            'employee_id' => 7,
            'scheduled_date' => '2026-07-10',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $shift->id = 20;
        $shift->setRelation('employee', $employee);

        $items = $this->invokePrivate('missingClockInItems', [
            new Collection([$shift]),
            new Collection(),
            ['7|2026-07-10' => true],
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            DisplayTimezone::name(),
        ]);

        $this->assertSame([], $items);
    }

    public function test_missing_clock_in_flags_late_unclocked_shift(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-late',
            'full_legal_name' => 'Late Starter',
            'email' => 'late@example.com',
        ]);
        $employee->id = 8;

        $shift = new EmployeeScheduleShift([
            'employee_id' => 8,
            'scheduled_date' => '2026-07-10',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $shift->id = 21;
        $shift->setRelation('employee', $employee);

        $items = $this->invokePrivate('missingClockInItems', [
            new Collection([$shift]),
            new Collection(),
            [],
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            DisplayTimezone::name(),
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('not clocked in yet', $items[0]['message']);
        $this->assertStringContainsString('/admin/employees/time-clock', $items[0]['url']);
        $this->assertStringContainsString('employee=emp-late', $items[0]['url']);
    }

    public function test_no_show_excludes_yesterday_day_shifts(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-noshow',
            'full_legal_name' => 'Yesterday No Show',
            'email' => 'noshow@example.com',
        ]);
        $employee->id = 12;

        $yesterdayShift = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-07-09',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $yesterdayShift->id = 30;
        $yesterdayShift->setRelation('employee', $employee);

        $overnightIntoToday = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-07-09',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '19:30',
            'end_time' => '03:00',
        ]);
        $overnightIntoToday->id = 31;
        $overnightIntoToday->setRelation('employee', $employee);

        $items = $this->invokePrivate('noShowAndSickItems', [
            new Collection([$yesterdayShift, $overnightIntoToday]),
            new Collection(),
            new Collection(),
            new Collection(),
            [],
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            DisplayTimezone::name(),
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('19:30', $items[0]['message']);
        $this->assertStringNotContainsString('09:00', $items[0]['message']);
    }

    public function test_birthday_notification_for_today(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-bday',
            'full_legal_name' => 'Birthday Person',
            'email' => 'birthday@example.com',
            'date_of_birth' => '1990-07-10',
        ]);
        $employee->id = 9;

        $items = $this->invokePrivate('birthdayItems', [
            new Collection([$employee]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('birthday today', $items[0]['message']);
        $this->assertStringContainsString('/admin/employees/profiles', $items[0]['url']);
        $this->assertStringContainsString('employee=emp-bday', $items[0]['url']);
    }

    public function test_pending_time_off_request_notification(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-time-off',
            'full_legal_name' => 'Mobile Requester',
            'email' => 'mobile@example.com',
        ]);
        $employee->id = 11;

        $request = new TimeOffRequest([
            'employee_id' => 11,
            'requested_date' => '2026-07-20',
            'reason' => 'Family event',
            'status' => TimeOffRequest::STATUS_PENDING,
        ]);
        $request->id = 42;
        $request->setRelation('employee', $employee);

        $items = $this->invokePrivate('pendingTimeOffItems', [
            new Collection([$request]),
            static fn (Employee $e): string => (string) $e->full_legal_name,
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('pending time off', $items[0]['message']);
        $this->assertStringContainsString('Mobile Requester', $items[0]['message']);
        $this->assertStringContainsString('/admin', $items[0]['url']);
        $this->assertStringContainsString('open_time_off_request=42', $items[0]['url']);
        $this->assertStringNotContainsString('weekly-schedule', $items[0]['url']);
        $this->assertSame(42, $items[0]['time_off_review']['id']);
        $this->assertSame('emp-time-off', $items[0]['time_off_review']['employee_public_id']);
        $this->assertSame('Family event', $items[0]['time_off_review']['reason']);
        $this->assertStringContainsString('/admin/time-off-requests/42/approve', $items[0]['time_off_review']['approve_url']);
        $this->assertStringContainsString('/admin/time-off-requests/42/reject', $items[0]['time_off_review']['reject_url']);
    }

    public function test_section_builds_summary_and_highlight(): void
    {
        $section = $this->invokePrivate('section', [
            'pending_leave',
            'Pending time off requests',
            [
                ['message' => 'A', 'url' => null, 'severity' => 'urgent', 'sort_at' => 1],
                ['message' => 'B', 'url' => null, 'severity' => 'info', 'sort_at' => 2],
            ],
        ]);

        $this->assertSame('2 pending leave requests', $section['summary']);
        $this->assertTrue($section['highlight']);
        $this->assertSame(2, $section['total_count']);
        $this->assertNull($section['hub_url']);
    }

    public function test_section_warning_items_are_not_highlighted(): void
    {
        $section = $this->invokePrivate('section', [
            'incomplete_onboarding',
            'Employees with incomplete onboarding requirements',
            [
                ['message' => 'A', 'url' => null, 'severity' => 'warning', 'sort_at' => 1],
            ],
        ]);

        $this->assertSame('1 employee with incomplete onboarding', $section['summary']);
        $this->assertFalse($section['highlight']);
    }

    public function test_workflow_omits_empty_columns_and_empty_sections(): void
    {
        $workflow = AdminDashboardNotifications::workflowColumns([
            [
                'key' => 'pending_leave',
                'title' => 'Pending time off requests',
                'items' => [['message' => 'A', 'url' => null, 'severity' => 'warning', 'sort_at' => 1]],
                'total_count' => 1,
                'unavailable' => false,
                'unavailable_reason' => null,
                'summary' => '1 leave request to review',
                'highlight' => true,
            ],
            [
                'key' => 'birthdays',
                'title' => 'Employee birthdays',
                'items' => [],
                'total_count' => 0,
                'unavailable' => false,
                'unavailable_reason' => null,
                'summary' => '0 items',
                'highlight' => false,
            ],
            [
                'key' => 'clocked_in',
                'title' => 'Staff currently clocked in',
                'items' => [],
                'total_count' => 0,
                'unavailable' => false,
                'unavailable_reason' => null,
                'summary' => '0 items',
                'highlight' => false,
            ],
            [
                'key' => 'incomplete_onboarding',
                'title' => 'Employees with incomplete onboarding requirements',
                'items' => [],
                'total_count' => 0,
                'unavailable' => false,
                'unavailable_reason' => null,
                'summary' => '0 items',
                'highlight' => false,
            ],
        ]);

        $this->assertSame(['requires_action'], array_column($workflow, 'key'));
        $this->assertCount(1, $workflow[0]['cards']);
        $this->assertSame('pending_leave', $workflow[0]['cards'][0]['key']);
    }

    public function test_workflow_keeps_only_populated_columns(): void
    {
        $workflow = AdminDashboardNotifications::workflowColumns([
            [
                'key' => 'pending_leave',
                'total_count' => 1,
                'unavailable' => false,
            ],
            [
                'key' => 'clocked_in',
                'total_count' => 2,
                'unavailable' => false,
            ],
            [
                'key' => 'open_shifts',
                'total_count' => 0,
                'unavailable' => false,
            ],
        ]);

        $this->assertSame(['requires_action', 'happening_today'], array_column($workflow, 'key'));
    }

    public function test_workflow_places_categories_in_expected_columns(): void
    {
        $workflow = AdminDashboardNotifications::workflowColumns([
            ['key' => 'expired_documents', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'pending_leave', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'unapproved_timesheets', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'schedule_conflicts', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'missing_clock_in', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'no_shows_sick', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'late_early_punches', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'overtime', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'clocked_in', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'birthdays_today', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'recently_joined', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'birthdays', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'upcoming_shifts', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'upcoming_renewals', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'incomplete_onboarding', 'total_count' => 1, 'unavailable' => false],
            ['key' => 'open_shifts', 'total_count' => 1, 'unavailable' => false],
        ]);

        $byColumn = [];
        foreach ($workflow as $column) {
            $byColumn[$column['key']] = array_column($column['cards'], 'key');
        }

        $this->assertSame(
            ['expired_documents', 'unapproved_timesheets', 'pending_leave', 'schedule_conflicts'],
            $byColumn['requires_action'],
        );
        $this->assertSame(
            ['missing_clock_in', 'no_shows_sick', 'late_early_punches', 'overtime', 'clocked_in', 'birthdays_today'],
            $byColumn['happening_today'],
        );
        $this->assertSame(
            ['recently_joined', 'birthdays', 'upcoming_shifts', 'upcoming_renewals'],
            $byColumn['upcoming'],
        );
        $this->assertSame(
            ['incomplete_onboarding', 'open_shifts'],
            $byColumn['clean_up'],
        );
    }

    public function test_missing_clock_in_skips_shifts_that_have_ended(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-ended',
            'full_legal_name' => 'Ended Shift',
            'email' => 'ended@example.com',
        ]);
        $employee->id = 21;

        $shift = new EmployeeScheduleShift([
            'employee_id' => 21,
            'scheduled_date' => '2026-07-10',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '06:00',
            'end_time' => '09:00',
        ]);
        $shift->id = 40;
        $shift->setRelation('employee', $employee);

        $items = $this->invokePrivate('missingClockInItems', [
            new Collection([$shift]),
            new Collection(),
            [],
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            DisplayTimezone::name(),
        ]);

        $this->assertSame([], $items);
    }

    public function test_late_early_excludes_yesterday_day_shifts(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-late-yest',
            'full_legal_name' => 'Yesterday Late',
            'email' => 'yl@example.com',
        ]);
        $employee->id = 22;

        $yesterdayShift = new EmployeeScheduleShift([
            'employee_id' => 22,
            'scheduled_date' => '2026-07-09',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $yesterdayShift->id = 41;
        $yesterdayShift->setRelation('employee', $employee);

        $clockIn = new TimeClockEntry([
            'employee_id' => 22,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-07-09 09:40:00', DisplayTimezone::name()),
        ]);
        $clockIn->id = 50;

        $items = $this->invokePrivate('lateEarlyItems', [
            new Collection([$yesterdayShift]),
            new Collection([$clockIn]),
            [],
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
            DisplayTimezone::now(),
            DisplayTimezone::name(),
        ]);

        $this->assertSame([], $items);
    }

    public function test_open_shifts_only_flags_missing_shift_pattern(): void
    {
        $noShift = new Employee([
            'public_id' => 'emp-no-shift',
            'full_legal_name' => 'No Shift',
            'email' => 'noshift@example.com',
            'department_id' => 1,
            'work_location_id' => 1,
            'shift_id' => null,
        ]);
        $noShift->id = 23;
        $noShift->setRelation('assignmentShifts', new Collection());

        $missingDept = new Employee([
            'public_id' => 'emp-no-dept',
            'full_legal_name' => 'No Dept',
            'email' => 'nodept@example.com',
            'department_id' => null,
            'work_location_id' => null,
            'shift_id' => 9,
        ]);
        $missingDept->id = 24;
        $missingDept->setRelation('assignmentShifts', new Collection());

        $items = $this->invokePrivate('openShiftItems', [
            new Collection([$noShift, $missingDept]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
        ]);

        $this->assertCount(1, $items);
        $this->assertStringContainsString('No Shift', $items[0]['message']);
        $this->assertStringContainsString('no shift pattern', $items[0]['message']);
    }

    public function test_incomplete_onboarding_does_not_include_shift_assignment_gap(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-onboard',
            'full_legal_name' => 'Almost Ready',
            'email' => 'ready@example.com',
            'employment_status' => 'active',
            'department_id' => 1,
            'work_location_id' => 1,
            'shift_id' => null,
            'police_check_uploaded' => true,
            'fit_to_work_uploaded' => true,
        ]);
        $employee->id = 25;
        $employee->setRelation('assignmentShifts', new Collection());

        $items = $this->invokePrivate('incompleteOnboardingItems', [
            new Collection([$employee]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
        ]);

        $this->assertSame([], $items);
    }

    public function test_schedule_conflicts_ignore_yesterday(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-conflict',
            'full_legal_name' => 'Conflict Person',
            'email' => 'conflict@example.com',
        ]);
        $employee->id = 26;

        $a = new EmployeeScheduleShift([
            'employee_id' => 26,
            'scheduled_date' => '2026-07-09',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '13:00',
        ]);
        $a->id = 42;
        $a->setRelation('employee', $employee);

        $b = new EmployeeScheduleShift([
            'employee_id' => 26,
            'scheduled_date' => '2026-07-09',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '12:00',
            'end_time' => '17:00',
        ]);
        $b->id = 43;
        $b->setRelation('employee', $employee);

        $items = $this->invokePrivate('scheduleConflictItems', [
            new Collection([$a, $b]),
            static fn (Employee $e): string => '/employee/'.$e->public_id,
            static fn (Employee $e): string => (string) $e->full_legal_name,
        ]);

        $this->assertSame([], $items);
    }

    /**
     * @param  list<mixed>  $args
     */
    private function invokePrivate(string $method, array $args): mixed
    {
        $reflection = new ReflectionMethod(AdminDashboardNotifications::class, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs(null, $args);
    }
}
