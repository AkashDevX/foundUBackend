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
}
