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
