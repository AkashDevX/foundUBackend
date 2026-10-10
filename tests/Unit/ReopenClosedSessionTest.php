<?php

namespace Tests\Unit;

use App\Exceptions\TimeClockException;
use App\Models\Employee;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use App\Services\TimeClockService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReopenClosedSessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('timesheet_approvals');
        Schema::dropIfExists('time_clock_entries');
        Schema::dropIfExists('employees');

        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->string('public_id')->nullable();
            $table->string('employment_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('time_clock_entries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->string('event_type', 32);
            $table->dateTime('clocked_at');
            $table->string('punch_source')->nullable();
            $table->dateTime('reopened_at')->nullable();
            $table->timestamps();
        });

        Schema::create('timesheet_approvals', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('clock_in_entry_id');
            $table->date('work_date')->nullable();
            $table->unsignedInteger('total_seconds')->default(0);
            $table->unsignedInteger('completed_sessions')->default(0);
            $table->string('status');
            $table->string('reviewed_by')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('timesheet_approvals');
        Schema::dropIfExists('time_clock_entries');
        Schema::dropIfExists('employees');

        parent::tearDown();
    }

    public function test_reopening_the_latest_clock_out_puts_the_shift_back_in_progress(): void
    {
        Carbon::setTestNow('2026-06-29 12:00:00');

        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => 'emp-1',
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $clockIn = TimeClockEntry::query()->create([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 08:00:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);
        $clockOut = TimeClockEntry::query()->create([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 09:15:00', 'UTC'),
            'punch_source' => TimeClockEntry::PUNCH_SOURCE_MANUAL,
        ]);

        TimesheetApproval::query()->create([
            'employee_id' => $employeeId,
            'clock_in_entry_id' => $clockIn->id,
            'work_date' => '2026-06-29',
            'total_seconds' => 4500,
            'completed_sessions' => 1,
            'status' => TimesheetApproval::STATUS_APPROVED,
            'reviewed_by' => 'Admin',
            'reviewed_at' => Carbon::parse('2026-06-29 10:00:00', 'UTC'),
            'review_notes' => 'Looks short',
        ]);

        $employee = Employee::query()->findOrFail($employeeId);
        $service = new TimeClockService;
        $service->reopenClosedSession($employee, (int) $clockIn->id, (int) $clockOut->id);

        $this->assertNull(TimeClockEntry::query()->find($clockOut->id));

        $clockIn->refresh();
        $this->assertNotNull($clockIn->reopened_at);

        $session = $service->openSessionFor($employee);
        $this->assertNotNull($session);
        $this->assertSame((int) $clockIn->id, (int) $session['clock_in']->id);

        $approval = TimesheetApproval::query()->where('clock_in_entry_id', $clockIn->id)->first();
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $approval?->status);
        $this->assertSame(0, (int) $approval?->completed_sessions);
        $this->assertNull($approval?->reviewed_by);
        $this->assertNull($approval?->review_notes);

        Carbon::setTestNow();
    }

    public function test_an_older_clock_out_cannot_be_reopened_after_a_later_shift(): void
    {
        $employeeId = DB::table('employees')->insertGetId([
            'public_id' => 'emp-2',
            'employment_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $firstIn = TimeClockEntry::query()->create([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 08:00:00', 'UTC'),
        ]);
        $firstOut = TimeClockEntry::query()->create([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        ]);
        TimeClockEntry::query()->create([
            'employee_id' => $employeeId,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 13:00:00', 'UTC'),
        ]);

        $employee = Employee::query()->findOrFail($employeeId);

        try {
            (new TimeClockService)->reopenClosedSession($employee, (int) $firstIn->id, (int) $firstOut->id);
            $this->fail('An older clock-out should not be reopened.');
        } catch (TimeClockException $e) {
            $this->assertSame('later_clock_activity', $e->errorCode);
        }

        $this->assertNotNull(TimeClockEntry::query()->find($firstOut->id));
        $this->assertNull($firstIn->fresh()?->reopened_at);
    }
}
