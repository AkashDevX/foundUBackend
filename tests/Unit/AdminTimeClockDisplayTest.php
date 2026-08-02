<?php

namespace Tests\Unit;

use App\Models\TimeClockEntry;
use App\Support\AdminTimeClockDisplay;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminTimeClockDisplayTest extends TestCase
{
    public function test_format_duration_hours_and_minutes(): void
    {
        $this->assertSame('2h 05m', AdminTimeClockDisplay::formatDuration(7500));
        $this->assertSame('45m', AdminTimeClockDisplay::formatDuration(2700));
        $this->assertSame('< 1m', AdminTimeClockDisplay::formatDuration(0));
    }

    public function test_summarize_work_sessions_pairs_clock_in_and_out(): void
    {
        Carbon::setTestNow('2026-05-24 18:00:00');
        config(['app.display_timezone' => 'UTC']);

        $clockIn = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-05-24 09:00:00'),
        ]);
        $clockIn->id = 1;
        $clockOut = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-05-24 17:30:00'),
        ]);
        $clockOut->id = 2;

        $summary = AdminTimeClockDisplay::summarizeWorkSessions(new Collection([$clockOut, $clockIn]));

        $this->assertSame(1, $summary['completed_sessions']);
        $this->assertSame(8 * 3600 + 30 * 60, $summary['total_seconds']);
        $this->assertSame('8h 30m', $summary['hours_by_entry_id'][2]['label']);
        $this->assertSame('9:00 AM → 5:30 PM', $summary['hours_by_entry_id'][2]['range_label']);
        $this->assertArrayNotHasKey(1, $summary['hours_by_entry_id']);

        Carbon::setTestNow();
    }

    public function test_open_shift_adds_running_duration(): void
    {
        Carbon::setTestNow('2026-05-24 12:00:00');

        $clockIn = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-05-24 10:00:00'),
        ]);
        $clockIn->id = 10;

        $summary = AdminTimeClockDisplay::summarizeWorkSessions(new Collection([$clockIn]));

        $this->assertSame(0, $summary['completed_sessions']);
        $this->assertSame(2 * 3600, $summary['total_seconds']);
        $this->assertTrue($summary['hours_by_entry_id'][10]['is_open']);

        Carbon::setTestNow();
    }

    public function test_punch_map_payload_returns_null_without_gps(): void
    {
        $entry = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
            'clocked_at' => Carbon::parse('2026-06-29 09:00:00', 'UTC'),
        ]);

        $this->assertNull(AdminTimeClockDisplay::punchMapPayload($entry));
    }

    public function test_punch_map_payload_marks_outside_geofence(): void
    {
        config(['app.display_timezone' => 'UTC']);

        $entry = new TimeClockEntry([
            'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
            'clocked_at' => Carbon::parse('2026-06-29 17:00:00', 'UTC'),
            'device_latitude' => -27.48,
            'device_longitude' => 153.03,
            'expected_latitude' => -27.4698,
            'expected_longitude' => 153.0251,
            'distance_from_site_meters' => 180,
            'allowed_radius_meters' => 100,
            'within_geofence' => false,
        ]);

        $payload = AdminTimeClockDisplay::punchMapPayload($entry);

        $this->assertIsArray($payload);
        $this->assertFalse($payload['within_geofence']);
        $this->assertSame('outside', $payload['icon_tone']);
        $this->assertSame('Outside designated radius', $payload['geofence_label']);
        $this->assertSame('Clock out', $payload['event_label']);
    }

    public function test_resolve_distance_meters_computes_from_coordinates_when_column_null(): void
    {
        $entry = new TimeClockEntry([
            'device_latitude' => -27.47,
            'device_longitude' => 153.02,
            'expected_latitude' => -27.4698,
            'expected_longitude' => 153.0251,
            'distance_from_site_meters' => null,
        ]);

        $meters = AdminTimeClockDisplay::resolveDistanceMeters($entry);

        $this->assertNotNull($meters);
        $this->assertGreaterThan(0, $meters);
        $this->assertSame((string) (int) round($meters), AdminTimeClockDisplay::formatDistanceMetersInteger($entry));
    }
}
