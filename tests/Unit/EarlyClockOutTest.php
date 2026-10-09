<?php

namespace Tests\Unit;

use App\Exceptions\TimeClockException;
use App\Models\EarlyClockOut;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Support\DisplayTimezone;
use App\Support\EarlyClockOutGate;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EarlyClockOutTest extends TestCase
{
    private string $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = (string) config('database.default');
        $this->dropTables();
        $this->createTables();
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    public function test_clock_out_at_the_scheduled_end_is_allowed(): void
    {
        EarlyClockOutGate::assertForShift($this->employee(), $this->shift(), null, $this->at('17:00'));
        $this->assertSame(0, EarlyClockOut::on($this->connection)->count());
    }

    public function test_leaving_early_without_a_note_does_not_queue_a_request(): void
    {
        try {
            EarlyClockOutGate::assertForShift($this->employee(), $this->shift(), '  ', $this->at('16:20'));
            $this->fail('An early clock-out without a note should be blocked.');
        } catch (TimeClockException $e) {
            $this->assertSame(EarlyClockOutGate::ISSUE_NOTE_REQUIRED, $e->errorCode);
            EarlyClockOutGate::persistBlockedAttempt($e);
        }

        $this->assertSame(0, EarlyClockOut::on($this->connection)->count());
    }

    public function test_leaving_early_with_a_note_waits_for_approval(): void
    {
        $employee = $this->employee();
        $shift = $this->shift();

        try {
            EarlyClockOutGate::assertForShift($employee, $shift, 'Finished the site early', $this->at('16:20'));
            $this->fail('An early clock-out should wait for approval.');
        } catch (TimeClockException $e) {
            $this->assertSame(EarlyClockOutGate::ISSUE_APPROVAL_PENDING, $e->errorCode);
            $this->assertStringContainsString('5:00 PM', $e->getMessage());
            EarlyClockOutGate::persistBlockedAttempt($e);
        }

        $row = EarlyClockOut::on($this->connection)->first();
        $this->assertInstanceOf(EarlyClockOut::class, $row);
        $this->assertSame(EarlyClockOut::STATUS_PENDING, $row->status);
        $this->assertSame('Finished the site early', $row->employee_note);

        $row->status = EarlyClockOut::STATUS_CLEARED;
        $row->save();

        EarlyClockOutGate::assertForShift($employee, $shift, null, $this->at('16:30'));

        $this->assertSame(
            [
                'needs_approval' => false,
                'approved' => true,
                'shift_end_label' => '5:00 PM',
            ],
            EarlyClockOutGate::mobileStatusForShift($employee, $shift, $this->at('16:30')),
        );
    }

    private function employee(): Employee
    {
        $employee = new Employee;
        $employee->id = 8;
        $employee->setConnection($this->connection);

        return $employee;
    }

    private function shift(): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'scheduled_date' => '2026-06-16',
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $shift->id = 12;
        $shift->setConnection($this->connection);

        return $shift;
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-06-16 '.$time, DisplayTimezone::name());
    }

    private function createTables(): void
    {
        Schema::connection($this->connection)->create('early_clock_outs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->unsignedBigInteger('schedule_shift_id')->nullable();
            $table->date('scheduled_date');
            $table->timestamp('shift_ends_at');
            $table->string('status', 20)->default('pending');
            $table->timestamp('attempted_at');
            $table->text('employee_note');
            $table->unsignedSmallInteger('minutes_early')->default(0);
            $table->string('cleared_by', 200)->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    private function dropTables(): void
    {
        Schema::connection($this->connection)->dropIfExists('early_clock_outs');
    }
}
