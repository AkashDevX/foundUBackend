<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeOffRequest;
use App\Support\AdminTimeOffRequestReview;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AdminTimeOffRequestReviewTest extends TestCase
{
    public function test_reject_attributes_trim_note(): void
    {
        $attrs = AdminTimeOffRequestReview::rejectAttributes('  Too short staffed  ', 'Manager');

        $this->assertSame(TimeOffRequest::STATUS_REJECTED, $attrs['status']);
        $this->assertSame('Too short staffed', $attrs['decision_note']);
        $this->assertSame('Manager', $attrs['reviewed_by']);
        $this->assertNotNull($attrs['reviewed_at']);
    }

    public function test_reject_attributes_blank_note_becomes_null(): void
    {
        $attrs = AdminTimeOffRequestReview::rejectAttributes('   ', 'Manager');

        $this->assertNull($attrs['decision_note']);
    }

    public function test_approve_attributes_link_schedule_entry(): void
    {
        $entry = new EmployeeScheduleShift([
            'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF,
            'leave_record_id' => 9,
        ]);
        $entry->id = 55;

        $attrs = AdminTimeOffRequestReview::approveAttributes('Reviewer', $entry);

        $this->assertSame(TimeOffRequest::STATUS_APPROVED, $attrs['status']);
        $this->assertSame(55, $attrs['schedule_shift_id']);
        $this->assertSame(9, $attrs['leave_record_id']);
        $this->assertSame('Reviewer', $attrs['reviewed_by']);
    }

    public function test_day_off_payload_from_pending_request(): void
    {
        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Alex',
        ]);
        $employee->id = 3;

        $request = new TimeOffRequest([
            'employee_id' => 3,
            'requested_date' => '2026-08-01',
            'reason' => 'Travel',
            'status' => TimeOffRequest::STATUS_PENDING,
        ]);
        $request->id = 17;

        $payload = AdminTimeOffRequestReview::dayOffPayload($request, $employee, 4, 7.6);

        $this->assertSame('emp-1', $payload['employee_public_id']);
        $this->assertSame('2026-08-01', $payload['scheduled_date']);
        $this->assertSame(EmployeeScheduleShift::TYPE_TIME_OFF, $payload['entry_type']);
        $this->assertSame('Travel', $payload['notes']);
        $this->assertSame(4, $payload['leave_type_id']);
        $this->assertSame(7.6, $payload['leave_hours']);
        $this->assertSame(17, $payload['time_off_request_id']);
    }

    public function test_day_off_payload_omits_hours_without_leave_type(): void
    {
        $employee = new Employee(['public_id' => 'emp-2']);
        $request = new TimeOffRequest([
            'requested_date' => '2026-08-02',
            'reason' => null,
        ]);
        $request->id = 18;

        $payload = AdminTimeOffRequestReview::dayOffPayload($request, $employee, null, 8.0);

        $this->assertNull($payload['leave_type_id']);
        $this->assertNull($payload['leave_hours']);
    }

    public function test_time_off_request_routes_registered(): void
    {
        $this->assertTrue(Route::has('admin.time-off-requests.approve'));
        $this->assertTrue(Route::has('admin.time-off-requests.reject'));
    }
}
