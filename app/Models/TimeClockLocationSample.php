<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'clock_in_entry_id',
    'recorded_at',
    'latitude',
    'longitude',
    'accuracy_meters',
    'work_location_id',
    'distance_from_site_meters',
    'allowed_radius_meters',
    'within_geofence',
])]
class TimeClockLocationSample extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function clockInEntry(): BelongsTo
    {
        return $this->belongsTo(TimeClockEntry::class, 'clock_in_entry_id');
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toMobilePayload(): array
    {
        return [
            'id' => $this->id,
            'clock_in_entry_id' => $this->clock_in_entry_id,
            'recorded_at' => $this->recorded_at?->toIso8601String(),
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'accuracy_meters' => $this->accuracy_meters !== null ? (float) $this->accuracy_meters : null,
            'distance_from_site_meters' => $this->distance_from_site_meters !== null
                ? (float) $this->distance_from_site_meters
                : null,
            'allowed_radius_meters' => $this->allowed_radius_meters,
            'within_geofence' => (bool) $this->within_geofence,
        ];
    }

    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
            'accuracy_meters' => 'float',
            'distance_from_site_meters' => 'float',
            'allowed_radius_meters' => 'integer',
            'within_geofence' => 'boolean',
        ];
    }
}
