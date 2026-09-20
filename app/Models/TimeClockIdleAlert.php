<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'clock_in_entry_id',
    'started_at',
    'detected_at',
    'idle_minutes',
    'center_latitude',
    'center_longitude',
    'max_displacement_meters',
    'status',
    'employee_acknowledged_at',
    'cleared_at',
])]
class TimeClockIdleAlert extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_CLEARED = 'cleared';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function clockInEntry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class, 'clock_in_entry_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function toMobilePayload(): array
    {
        return [
            'id' => $this->id,
            'clock_in_entry_id' => $this->clock_in_entry_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'detected_at' => $this->detected_at?->toIso8601String(),
            'idle_minutes' => (int) $this->idle_minutes,
            'center_latitude' => $this->center_latitude !== null ? (float) $this->center_latitude : null,
            'center_longitude' => $this->center_longitude !== null ? (float) $this->center_longitude : null,
            'max_displacement_meters' => $this->max_displacement_meters !== null
                ? (float) $this->max_displacement_meters
                : null,
            'status' => $this->status,
            'employee_acknowledged_at' => $this->employee_acknowledged_at?->toIso8601String(),
            'message' => sprintf(
                'Little movement detected for about %d minutes. Please confirm you are still working.',
                (int) $this->idle_minutes,
            ),
        ];
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'detected_at' => 'datetime',
            'idle_minutes' => 'integer',
            'center_latitude' => 'float',
            'center_longitude' => 'float',
            'max_displacement_meters' => 'float',
            'employee_acknowledged_at' => 'datetime',
            'cleared_at' => 'datetime',
        ];
    }
}
