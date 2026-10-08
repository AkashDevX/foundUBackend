<?php

namespace App\Models;

use App\Support\ClockInGrace;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'schedule_shift_id',
    'scheduled_date',
    'shift_starts_at',
    'kind',
    'status',
    'attempted_at',
    'grace_minutes',
    'minutes_outside',
    'cleared_by',
    'cleared_at',
    'admin_note',
])]
class ClockInException extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CLEARED = 'cleared';

    public const STATUS_USED = 'used';

    public const STATUS_VOID = 'void';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function kindLabel(): string
    {
        return $this->kind === ClockInGrace::KIND_EARLY ? 'Early' : 'Late';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_CLEARED => 'Allowed',
            self::STATUS_USED => 'Clocked in',
            self::STATUS_VOID => 'Dismissed',
            default => 'Needs approval',
        };
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'shift_starts_at' => 'datetime',
            'attempted_at' => 'datetime',
            'cleared_at' => 'datetime',
            'grace_minutes' => 'integer',
            'minutes_outside' => 'integer',
        ];
    }
}
