<?php

namespace Tests\Unit;

use App\Support\GeoDistance;
use PHPUnit\Framework\TestCase;

class GeoDistanceTest extends TestCase
{
    public function test_same_point_is_zero_meters(): void
    {
        $distance = GeoDistance::metersBetween(-27.4698, 153.0251, -27.4698, 153.0251);

        $this->assertEqualsWithDelta(0.0, $distance, 0.01);
    }

    public function test_known_separation_is_reasonable(): void
    {
        // ~111 km per degree latitude at the equator; 0.001° ≈ 111 m
        $distance = GeoDistance::metersBetween(0.0, 0.0, 0.001, 0.0);

        $this->assertGreaterThan(100.0, $distance);
        $this->assertLessThan(120.0, $distance);
    }
}
