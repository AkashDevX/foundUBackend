<?php

namespace Tests\Unit;

use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Tests\TestCase;

class DisplayTimezoneTest extends TestCase
{
    public function test_formats_utc_instant_in_display_timezone(): void
    {
        config(['app.display_timezone' => 'Australia/Brisbane']);

        $at = Carbon::parse('2026-05-24 11:45:26', 'UTC');

        $this->assertSame('9:45 PM', DisplayTimezone::format($at, 'g:i A'));
    }

    public function test_brisbane_stays_on_aest_during_sydney_daylight_saving(): void
    {
        config(['app.display_timezone' => 'Australia/Brisbane']);

        // 8 Oct 2026 is AEDT in Sydney (UTC+11) and AEST in Brisbane (UTC+10).
        $at = Carbon::parse('2026-10-08 01:30:00', 'UTC');

        $this->assertSame('11:30 AM', DisplayTimezone::format($at, 'g:i A'));
        $this->assertSame('2026-10-08', $at->copy()->timezone(DisplayTimezone::name())->toDateString());
    }
}
