<?php

namespace Tests\Unit;

use App\Support\LocationIdleDetector;
use Tests\TestCase;

class LocationIdleDetectorTest extends TestCase
{
    public function test_stationary_samples_are_idle(): void
    {
        $samples = [
            ['latitude' => -27.4700, 'longitude' => 153.0250],
            ['latitude' => -27.4701, 'longitude' => 153.0251],
            ['latitude' => -27.4699, 'longitude' => 153.0249],
            ['latitude' => -27.4700, 'longitude' => 153.0250],
        ];

        $result = LocationIdleDetector::analyze($samples, 40.0);

        $this->assertTrue($result['is_idle']);
        $this->assertLessThanOrEqual(40.0, $result['max_displacement_meters']);
        $this->assertSame(4, $result['sample_count']);
        $this->assertNotNull($result['center_latitude']);
        $this->assertNotNull($result['center_longitude']);
    }

    public function test_walking_samples_are_not_idle(): void
    {
        // ~111m per 0.001 deg latitude — these points span well over 40m.
        $samples = [
            ['latitude' => -27.4700, 'longitude' => 153.0250],
            ['latitude' => -27.4710, 'longitude' => 153.0250],
            ['latitude' => -27.4720, 'longitude' => 153.0250],
            ['latitude' => -27.4730, 'longitude' => 153.0250],
        ];

        $result = LocationIdleDetector::analyze($samples, 40.0);

        $this->assertFalse($result['is_idle']);
        $this->assertGreaterThan(40.0, $result['max_displacement_meters']);
    }

    public function test_empty_samples_are_not_idle(): void
    {
        $result = LocationIdleDetector::analyze([], 40.0);

        $this->assertFalse($result['is_idle']);
        $this->assertSame(0, $result['sample_count']);
        $this->assertNull($result['center_latitude']);
    }
}
