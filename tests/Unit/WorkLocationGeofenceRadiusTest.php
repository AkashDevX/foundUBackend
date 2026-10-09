<?php

namespace Tests\Unit;

use App\Models\WorkLocation;
use App\Services\TimeClockService;
use Tests\TestCase;

class WorkLocationGeofenceRadiusTest extends TestCase
{
    public function test_each_work_location_keeps_its_own_radius(): void
    {
        $small = new WorkLocation(['geofence_radius_meters' => 50]);
        $medium = new WorkLocation(['geofence_radius_meters' => 100]);
        $large = new WorkLocation(['geofence_radius_meters' => 300]);

        $this->assertSame(50, $small->resolvedGeofenceRadiusMeters());
        $this->assertSame(100, $medium->resolvedGeofenceRadiusMeters());
        $this->assertSame(300, $large->resolvedGeofenceRadiusMeters());
    }

    public function test_missing_radius_falls_back_to_the_previous_300_metre_default(): void
    {
        config(['time_clock.geofence_radius_meters' => 300]);

        $location = new WorkLocation;
        $service = new TimeClockService;

        $this->assertSame(300, $location->resolvedGeofenceRadiusMeters());
        $this->assertSame(300, $service->radiusForWorkLocation($location));
        $this->assertSame(300, $service->radiusForWorkLocation(null));
    }

    public function test_radius_is_clamped_to_the_allowed_range(): void
    {
        config(['time_clock.geofence_radius_meters' => 300]);

        $tooSmall = new WorkLocation(['geofence_radius_meters' => 1]);
        $tooLarge = new WorkLocation(['geofence_radius_meters' => 9000]);

        $this->assertSame(300, $tooSmall->resolvedGeofenceRadiusMeters());
        $this->assertSame(5000, $tooLarge->resolvedGeofenceRadiusMeters());
    }
}
