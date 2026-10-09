<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'latitude', 'longitude', 'geofence_radius_meters', 'notes', 'is_active'])]
class WorkLocation extends Model
{
    public const GEOFENCE_RADIUS_MIN = 10;

    public const GEOFENCE_RADIUS_MAX = 5_000;

    public const GEOFENCE_RADIUS_DEFAULT = 300;

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'geofence_radius_meters' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Radius used for clock-in and automatic clock-out at this site.
     * Falls back to the global time-clock default when the column is empty.
     */
    public function resolvedGeofenceRadiusMeters(): int
    {
        $raw = $this->geofence_radius_meters;
        $radius = is_numeric($raw) ? (int) $raw : 0;
        if ($radius < self::GEOFENCE_RADIUS_MIN) {
            $radius = (int) config('time_clock.geofence_radius_meters', self::GEOFENCE_RADIUS_DEFAULT);
        }

        return max(self::GEOFENCE_RADIUS_MIN, min($radius, self::GEOFENCE_RADIUS_MAX));
    }
}
