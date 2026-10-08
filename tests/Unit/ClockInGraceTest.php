<?php

namespace Tests\Unit;

use App\Exceptions\TimeClockException;
use App\Models\ClockInException;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Support\ClockInGrace;
use App\Support\ClockInGraceGate;
use App\Support\ClockInGraceSettings;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ClockInGraceTest extends TestCase
{
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = (string) config('database.default');
        ClockInGraceSettings::forget();
        $this->dropTables();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        ClockInGraceSettings::forget();
        $this->dropTables();

        parent::tearDown();
    }

    public function test_twenty_minute_window_around_a_nine_am_shift(): void
    {
        $start = Carbon::parse('2026-06-16 09:00:00', DisplayTimezone::name());
        $bounds = ClockInGrace::bounds($start, 20);

        $this->assertSame('8:40 AM', $bounds['earliest']->format('g:i A'));
        $this->assertSame('9:20 AM', $bounds['latest']->format('g:i A'));
        $this->assertNull(ClockInGrace::deviation($this->at('08:40'), $start, 20));
        $this->assertNull(ClockInGrace::deviation($this->at('09:00'), $start, 20));
        $this->assertNull(ClockInGrace::deviation($this->at('09:20'), $start, 20));
        $this->assertNull(ClockInGrace::deviation($this->at('09:20:45'), $start, 20));
        $this->assertSame(ClockInGrace::KIND_EARLY, ClockInGrace::deviation($this->at('08:39'), $start, 20));
        $this->assertSame(ClockInGrace::KIND_LATE, ClockInGrace::deviation($this->at('09:21'), $start, 20));
    }

    public function test_cleared_exception_allows_a_punch_outside_the_window(): void
    {
        $open = ClockInGrace::decision(ClockInGrace::KIND_LATE, ClockInGrace::POLICY_EXCEPTION, null);
        $this->assertTrue($open['blocks']);
        $this->assertSame(ClockInGrace::ISSUE_EXCEPTION_PENDING, $open['issue']);
        $this->assertTrue($open['record_exception']);

        $cleared = ClockInGrace::decision(ClockInGrace::KIND_LATE, ClockInGrace::POLICY_EXCEPTION, ClockInGrace::CLEARANCE_CLEARED);
        $this->assertFalse($cleared['blocks']);

        $prevented = ClockInGrace::decision(ClockInGrace::KIND_EARLY, ClockInGrace::POLICY_PREVENT, null);
        $this->assertSame(ClockInGrace::ISSUE_TOO_EARLY, $prevented['issue']);
        $this->assertFalse($prevented['record_exception']);
    }

    public function test_messages_name_the_window_edges(): void
    {
        $start = Carbon::parse('2026-06-16 09:00:00', DisplayTimezone::name());
        $bounds = ClockInGrace::bounds($start, 20);

        $early = ClockInGrace::blockedMessage(
            ClockInGrace::ISSUE_EXCEPTION_PENDING,
            ClockInGrace::KIND_EARLY,
            20,
            $bounds['start'],
            $bounds['earliest'],
            $bounds['latest'],
        );
        $late = ClockInGrace::blockedMessage(
            ClockInGrace::ISSUE_TOO_LATE,
            ClockInGrace::KIND_LATE,
            20,
            $bounds['start'],
            $bounds['earliest'],
            $bounds['latest'],
        );

        $this->assertStringContainsString('8:40 AM', $early);
        $this->assertStringContainsString('clear the exception', $early);
        $this->assertStringContainsString('9:20 AM', $late);
    }

    public function test_exception_policy_blocks_until_an_admin_clears_it(): void
    {
        $this->createTables();
        ClockInGraceSettings::save($this->connection, 20, ClockInGrace::POLICY_EXCEPTION);

        $employee = new Employee;
        $employee->id = 4;
        $shift = $this->shift();

        try {
            ClockInGraceGate::assertAllowsClockIn($employee, $shift, $this->at('08:30'));
            $this->fail('An early clock-in should be blocked.');
        } catch (TimeClockException $e) {
            $this->assertSame(ClockInGrace::ISSUE_EXCEPTION_PENDING, $e->errorCode);
            $this->assertStringContainsString('8:40 AM', $e->getMessage());
            ClockInGraceGate::persistBlockedAttempt($e);
        }

        $this->assertSame(1, ClockInException::on($this->connection)->count());
        $row = ClockInException::on($this->connection)->first();
        $this->assertSame(ClockInException::STATUS_PENDING, $row?->status);
        $this->assertSame(ClockInGrace::KIND_EARLY, $row?->kind);

        ClockInGraceGate::persistBlockedAttempt($this->blockedAttempt($employee, $shift, '08:25'));
        $this->assertSame(1, ClockInException::on($this->connection)->count());

        $row?->forceFill([
            'status' => ClockInException::STATUS_CLEARED,
            'cleared_by' => 'Alex Admin',
            'cleared_at' => now('UTC'),
        ])->save();

        ClockInGraceGate::assertAllowsClockIn($employee, $shift, $this->at('08:25'));

        ClockInGraceGate::noteSuccessfulClockIn($employee, $shift);
        $this->assertSame(
            ClockInException::STATUS_USED,
            ClockInException::on($this->connection)->first()?->status,
        );
    }

    public function test_prevent_policy_blocks_without_queueing_an_exception(): void
    {
        $this->createTables();
        ClockInGraceSettings::save($this->connection, 20, ClockInGrace::POLICY_PREVENT);

        $employee = new Employee;
        $employee->id = 4;
        $shift = $this->shift();

        ClockInGraceGate::assertAllowsClockIn($employee, $shift, $this->at('09:20'));

        try {
            ClockInGraceGate::assertAllowsClockIn($employee, $shift, $this->at('09:21'));
            $this->fail('A late clock-in should be blocked.');
        } catch (TimeClockException $e) {
            $this->assertSame(ClockInGrace::ISSUE_TOO_LATE, $e->errorCode);
            ClockInGraceGate::persistBlockedAttempt($e);
        }

        $this->assertSame(0, ClockInException::on($this->connection)->count());
    }

    private function blockedAttempt(Employee $employee, EmployeeScheduleShift $shift, string $time): TimeClockException
    {
        try {
            ClockInGraceGate::assertAllowsClockIn($employee, $shift, $this->at($time));
        } catch (TimeClockException $e) {
            return $e;
        }

        $this->fail('Expected the clock-in to be blocked.');
    }

    private function shift(): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'scheduled_date' => '2026-06-16',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $shift->id = 9;

        return $shift;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-06-16 '.$time, DisplayTimezone::name());
    }

    private function createTables(): void
    {
        Schema::connection($this->connection)->create('time_clock_settings', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('grace_minutes')->default(20);
            $table->string('outside_policy', 20)->default('exception');
            $table->timestamps();
        });

        Schema::connection($this->connection)->create('clock_in_exceptions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('schedule_shift_id')->nullable();
            $table->date('scheduled_date');
            $table->timestamp('shift_starts_at');
            $table->string('kind', 10);
            $table->string('status', 20)->default('pending');
            $table->timestamp('attempted_at');
            $table->unsignedSmallInteger('grace_minutes');
            $table->unsignedSmallInteger('minutes_outside')->default(0);
            $table->string('cleared_by', 200)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        ClockInGraceSettings::forget();
    }

    private function dropTables(): void
    {
        Schema::connection($this->connection)->dropIfExists('clock_in_exceptions');
        Schema::connection($this->connection)->dropIfExists('time_clock_settings');
    }
}
