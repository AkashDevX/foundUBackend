<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use App\Support\AdminTimesheetApproval;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminTimesheetApprovalTest extends TestCase
{
    public function test_build_rows_marks_days_without_approval_as_pending(): void
    {
        config(['app.display_timezone' => 'UTC']);

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
        ]);
        $employee->id = 1;

        $workDate = Carbon::parse('2026-06-29', 'UTC');
        $clockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => $workDate->copy()->setTime(9, 0),
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => $workDate->copy()->setTime(11, 0),
        ]);

        $clockIn->id = 7;
        $clockOut->id = 8;

        $employee->setRelation('timeClockEntries', new Collection([$clockOut, $clockIn]));

        $rows = AdminTimesheetApproval::buildRows(
            new Collection([$employee]),
            new Collection(),
            'pending'
        );

        $this->assertCount(1, $rows);
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $rows[0]['status']);
        $this->assertSame('2026-06-29', $rows[0]['work_date']);
        $this->assertSame('2h 00m', $rows[0]['total_hours_label']);
        $this->assertSame(1, $rows[0]['completed_sessions']);
    }

    public function test_build_rows_respects_approved_status_filter(): void
    {
        config(['app.display_timezone' => 'UTC']);

        $employee = new Employee(['email' => 'b@example.com']);
        $employee->id = 2;
        $workDate = Carbon::parse('2026-06-29', 'UTC');

        $clockIn = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => $workDate->copy()->setTime(8, 0),
        ]);
        $clockOut = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => $workDate->copy()->setTime(9, 0),
        ]);
        $clockIn->id = 7;
        $clockOut->id = 8;

        $entries = new Collection([$clockIn, $clockOut]);

        $employee->setRelation('timeClockEntries', $entries);
        $dayKey = array_key_first(AdminTimesheetApproval::groupEntriesByDay($entries));

        $approval = new TimesheetApproval([
            'employee_id' => 2,
            'clock_in_entry_id' => 7,
            'work_date' => $dayKey,
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);
        $approval->id = 10;

        $pendingRows = AdminTimesheetApproval::buildRows(new Collection([$employee]), new Collection([$approval]), 'pending');
        $approvedRows = AdminTimesheetApproval::buildRows(new Collection([$employee]), new Collection([$approval]), 'approved');

        $this->assertCount(0, $pendingRows);
        $this->assertCount(1, $approvedRows);
        $this->assertSame(TimesheetApproval::STATUS_APPROVED, $approvedRows[0]['status']);
    }
}
