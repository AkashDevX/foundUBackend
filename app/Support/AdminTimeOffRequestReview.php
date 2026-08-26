<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeOffRequest;

/**
 * Shared attribute builders for dashboard time-off approve/reject.
 */
final class AdminTimeOffRequestReview
{
    /**
     * @return array{status: string, decision_note: string|null, reviewed_by: string|null, reviewed_at: \Illuminate\Support\Carbon}
     */
    public static function rejectAttributes(?string $decisionNote, ?string $reviewedBy): array
    {
        $note = $decisionNote !== null && trim($decisionNote) !== ''
            ? trim($decisionNote)
            : null;

        return [
            'status' => TimeOffRequest::STATUS_REJECTED,
            'decision_note' => $note,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
        ];
    }

    /**
     * @return array{status: string, reviewed_by: string|null, reviewed_at: \Illuminate\Support\Carbon, schedule_shift_id: int, leave_record_id: int|null}
     */
    public static function approveAttributes(
        ?string $reviewedBy,
        EmployeeScheduleShift $entry,
    ): array {
        return [
            'status' => TimeOffRequest::STATUS_APPROVED,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'schedule_shift_id' => $entry->id,
            'leave_record_id' => $entry->leave_record_id,
        ];
    }

    /**
     * Payload shape expected by schedule day-off create + leave sync.
     *
     * @return array{
     *     employee_public_id: string,
     *     scheduled_date: string,
     *     entry_type: string,
     *     notes: string|null,
     *     leave_type_id: int|null,
     *     leave_hours: float|null,
     *     time_off_request_id: int,
     * }
     */
    public static function dayOffPayload(
        TimeOffRequest $request,
        Employee $employee,
        ?int $leaveTypeId,
        ?float $leaveHours,
    ): array {
        return [
            'employee_public_id' => $employee->public_id,
            'scheduled_date' => $request->requested_date?->toDateString() ?? '',
            'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF,
            'notes' => $request->reason,
            'leave_type_id' => $leaveTypeId,
            'leave_hours' => $leaveTypeId !== null ? $leaveHours : null,
            'time_off_request_id' => (int) $request->id,
        ];
    }
}
