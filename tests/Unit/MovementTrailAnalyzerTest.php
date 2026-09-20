<?php

namespace Tests\Unit;

use App\Support\MovementTrailAnalyzer;
use Tests\TestCase;

class MovementTrailAnalyzerTest extends TestCase
{
    public function test_computes_distance_and_segments_along_path(): void
    {
        $trail = [
            ['latitude' => -27.4700, 'longitude' => 153.0250, 'recorded_at' => '2026-07-03T06:00:00+00:00'],
            ['latitude' => -27.4710, 'longitude' => 153.0250, 'recorded_at' => '2026-07-03T06:10:00+00:00'],
            ['latitude' => -27.4720, 'longitude' => 153.0260, 'recorded_at' => '2026-07-03T06:20:00+00:00'],
        ];

        $stats = MovementTrailAnalyzer::analyze($trail);

        $this->assertSame(3, $stats['sample_count']);
        $this->assertGreaterThan(100, $stats['distance_meters']);
        $this->assertNotEmpty($stats['segments']);
        $this->assertArrayHasKey('bearing_degrees', $stats['segments'][0]);
    }

    public function test_detects_waiting_when_samples_cluster(): void
    {
        $trail = [
            ['latitude' => -27.47000, 'longitude' => 153.02500, 'recorded_at' => '2026-07-03T06:00:00+00:00'],
            ['latitude' => -27.47001, 'longitude' => 153.02501, 'recorded_at' => '2026-07-03T06:10:00+00:00'],
            ['latitude' => -27.47000, 'longitude' => 153.02500, 'recorded_at' => '2026-07-03T06:20:00+00:00'],
            ['latitude' => -27.47002, 'longitude' => 153.02499, 'recorded_at' => '2026-07-03T06:30:00+00:00'],
        ];

        $stats = MovementTrailAnalyzer::analyze($trail, [], 40.0);

        $this->assertTrue($stats['is_currently_waiting']);
        $this->assertGreaterThanOrEqual(20, $stats['waiting_minutes']);
    }
}
