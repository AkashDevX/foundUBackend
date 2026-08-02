<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'event_type',
    'clocked_at',
    'device_latitude',
    'device_longitude',
    'device_accuracy_meters',
    'work_location_id',
    'expected_latitude',
    'expected_longitude',
    'distance_from_site_meters',
    'allowed_radius_meters',
    'within_geofence',
    'punch_source',
    'department_id',
    'shift_id',
    'comment',
])]
class TimeClockEntry extends Model
{
    public const EVENT_CLOCK_IN = 'clock_in';

    public const EVENT_BREAK_START = 'break_start';

    public const EVENT_BREAK_END = 'break_end';

    public const EVENT_CLOCK_OUT = 'clock_out';

    /** Event types that mean the employee still has an open work session. */
    public const ON_SHIFT_EVENTS = [
        self::EVENT_CLOCK_IN,
        self::EVENT_BREAK_START,
        self::EVENT_BREAK_END,
    ];

    public const PUNCH_SOURCE_MANUAL = 'manual';

    public const PUNCH_SOURCE_AUTO_GEOFENCE_EXIT = 'auto_geofence_exit';

    public const PUNCH_SOURCE_AUTO_SHIFT_END = 'auto_shift_end';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toMobilePayload(): array
    {
        return [
            'id' => $this->id,
            'event_type' => $this->event_type,
            'clocked_at' => $this->clocked_at?->toIso8601String(),
            'device_latitude' => $this->device_latitude !== null ? (float) $this->device_latitude : null,
            'device_longitude' => $this->device_longitude !== null ? (float) $this->device_longitude : null,
            'device_accuracy_meters' => $this->device_accuracy_meters !== null ? (float) $this->device_accuracy_meters : null,
            'work_location_id' => $this->work_location_id,
            'expected_latitude' => $this->expected_latitude !== null ? (float) $this->expected_latitude : null,
            'expected_longitude' => $this->expected_longitude !== null ? (float) $this->expected_longitude : null,
            'distance_from_site_meters' => $this->distance_from_site_meters !== null ? (float) $this->distance_from_site_meters : null,
            'allowed_radius_meters' => $this->allowed_radius_meters,
            'within_geofence' => (bool) $this->within_geofence,
            'punch_source' => $this->punch_source ?? self::PUNCH_SOURCE_MANUAL,
            'department_id' => $this->department_id,
            'shift_id' => $this->shift_id,
            'comment' => $this->comment,
        ];
    }

    protected function casts(): array
    {
        return [
            'clocked_at' => 'datetime',
            'device_latitude' => 'float',
            'device_longitude' => 'float',
            'device_accuracy_meters' => 'float',
            'expected_latitude' => 'float',
            'expected_longitude' => 'float',
            'distance_from_site_meters' => 'float',
            'allowed_radius_meters' => 'integer',
            'within_geofence' => 'boolean',
        ];
    }
}
