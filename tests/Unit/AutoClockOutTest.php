<?php

namespace Tests\Unit;

use App\Support\AutoClockOut;
use Carbon\Carbon;
use Tests\TestCase;

class AutoClockOutTest extends TestCase
{
    private const TZ = 'Australia/Sydney';

    private function clockIn(string $local): Carbon
    {
        // The command feeds UTC timestamps (as stored in the DB).
        return Carbon::parse($local, self::TZ)->utc();
    }

    private function now(string $local): Carbon
    {
        return Carbon::parse($local, self::TZ);
    }

    public function test_closes_at_shift_end_once_grace_has_passed(): void
    {
        [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
            ['start_time' => '09:00', 'end_time' => '17:00'],
            $this->clockIn('2026-06-16 09:05'),
            $this->now('2026-06-16 17:15'),
            self::TZ,
            10,
            16,
        );

        $this->assertNotNull($closeAt);
        $this->assertSame('shift ended', $reason);
        $this->assertSame('2026-06-16 17:00:00', $closeAt->copy()->timezone(self::TZ)->toDateTimeString());
    }

    public function test_does_not_close_before_grace_window(): void
    {
        [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
            ['start_time' => '09:00', 'end_time' => '17:00'],
            $this->clockIn('2026-06-16 09:05'),
            $this->now('2026-06-16 17:05'), // only 5 min after end, grace is 10
            self::TZ,
            10,
            16,
        );

        $this->assertNull($closeAt);
        $this->assertSame('', $reason);
    }

    public function test_overnight_shift_end_rolls_to_next_day(): void
    {
        [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
            ['start_time' => '22:00', 'end_time' => '06:00'],
            $this->clockIn('2026-06-16 22:05'),
            $this->now('2026-06-17 06:15'),
            self::TZ,
            10,
            16,
        );

        $this->assertNotNull($closeAt);
        $this->assertSame('shift ended', $reason);
        $this->assertSame('2026-06-17 06:00:00', $closeAt->copy()->timezone(self::TZ)->toDateTimeString());
    }

    public function test_max_hours_cap_closes_session_without_a_shift(): void
    {
        [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
            null, // no resolvable shift window
            $this->clockIn('2026-06-16 08:00'),
            $this->now('2026-06-17 00:30'), // 16.5h later
            self::TZ,
            10,
            16,
        );

        $this->assertNotNull($closeAt);
        $this->assertSame('open over 16h', $reason);
        $this->assertSame('2026-06-17 00:00:00', $closeAt->copy()->timezone(self::TZ)->toDateTimeString());
    }

    public function test_no_shift_and_within_cap_stays_open(): void
    {
        [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
            null,
            $this->clockIn('2026-06-16 08:00'),
            $this->now('2026-06-16 20:00'), // 12h later, under the 16h cap
            self::TZ,
            10,
            16,
        );

        $this->assertNull($closeAt);
        $this->assertSame('', $reason);
    }

    public function test_running_late_within_shift_does_not_close(): void
    {
        [$closeAt] = AutoClockOut::resolveCloseAt(
            ['start_time' => '09:00', 'end_time' => '17:00'],
            $this->clockIn('2026-06-16 09:05'),
            $this->now('2026-06-16 14:00'), // mid-shift
            self::TZ,
            10,
            16,
        );

        $this->assertNull($closeAt);
    }
}
