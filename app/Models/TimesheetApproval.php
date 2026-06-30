<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'week_start',
    'week_end',
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

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
            'total_seconds' => 'integer',
            'completed_sessions' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
