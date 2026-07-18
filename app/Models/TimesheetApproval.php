<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'clock_in_entry_id',
    'work_date',
    'total_seconds',
    'completed_sessions',
    'status',
    'reviewed_by',
    'reviewed_at',
    'review_notes',
])]
class TimesheetApproval extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function clockInEntry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class, 'clock_in_entry_id');
    }

    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'total_seconds' => 'integer',
            'completed_sessions' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
