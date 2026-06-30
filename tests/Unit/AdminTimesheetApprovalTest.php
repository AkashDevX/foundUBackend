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
    public function test_build_rows_marks_weeks_without_approval_as_pending(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
        ]);
        $employee->id = 1;

        $weekStart = Carbon::now('Australia/Sydney')->startOfWeek(Carbon::MONDAY);
        $clockIn = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => $weekStart->copy()->setTime(9, 0)->utc(),
        ]);
        $clockOut = new TimeClockEntry([
            'employee_id' => 1,
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => $weekStart->copy()->setTime(11, 0)->utc(),
        ]);

        $employee->setRelation('timeClockEntries', new Collection([$clockOut, $clockIn]));

        $rows = AdminTimesheetApproval::buildRows(
            new Collection([$employee]),
            new Collection(),
            'pending'
        );

        $this->assertCount(1, $rows);
        $this->assertSame(TimesheetApproval::STATUS_PENDING, $rows[0]['status']);
        $this->assertSame('2h 00m', $rows[0]['total_hours_label']);
        $this->assertSame(1, $rows[0]['completed_sessions']);
    }

    public function test_build_rows_respects_approved_status_filter(): void
    {
        $employee = new Employee(['email' => 'b@example.com']);
        $employee->id = 2;
        $weekStart = Carbon::now('Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $entries = new Collection([
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
                'clocked_at' => $weekStart->copy()->setTime(8, 0)->utc(),
            ]),
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
                'clocked_at' => $weekStart->copy()->setTime(9, 0)->utc(),
            ]),
        ]);

        $employee->setRelation('timeClockEntries', $entries);
        $weekKey = array_key_first(AdminTimesheetApproval::groupEntriesByWeek($entries));

        $approval = new TimesheetApproval([
            'employee_id' => 2,
            'week_start' => $weekKey,
            'week_end' => AdminTimesheetApproval::weekEndForStart($weekKey),
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
