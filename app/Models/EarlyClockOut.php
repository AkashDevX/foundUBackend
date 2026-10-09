<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'schedule_shift_id',
    'scheduled_date',
    'shift_ends_at',
    'status',
    'attempted_at',
    'employee_note',
    'minutes_early',
    'cleared_by',
    'cleared_at',
    'admin_note',
])]
class EarlyClockOut extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_USED = 'used';

    public const STATUS_VOID = 'void';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'shift_ends_at' => 'datetime',
            'attempted_at' => 'datetime',
            'cleared_at' => 'datetime',
            'minutes_early' => 'integer',
        ];
    }
}
