<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'requested_date',
    'reason',
    'status',
    'decision_note',
    'reviewed_by',
    'reviewed_at',
    'leave_record_id',
    'schedule_shift_id',
])]
class TimeOffRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /** Day off was later removed / replaced with a shift by the admin. */
    public const STATUS_CANCELLED = 'cancelled';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveRecord::class, 'leave_record_id');
    }

    /**
     * Shape for the mobile app (GET /api/v1/time-off/requests).
     *
     * @return array<string, mixed>
     */
    public function toMobilePayload(): array
    {
        return [
            'id' => $this->id,
            'requested_date' => $this->requested_date?->toDateString(),
            'date_label' => $this->requested_date?->format('D, M j, Y') ?? '',
            'status' => $this->status,
            'reason' => $this->reason,
            'decision_note' => $this->decision_note,
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
        ];
    }

    protected function casts(): array
    {
        return [
            'requested_date' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }
}
