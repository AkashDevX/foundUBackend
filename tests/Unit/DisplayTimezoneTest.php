<?php

namespace Tests\Unit;

use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Tests\TestCase;

class DisplayTimezoneTest extends TestCase
{
    public function test_formats_utc_instant_in_display_timezone(): void
    {
        config(['app.display_timezone' => 'Australia/Sydney']);

        $at = Carbon::parse('2026-05-24 11:45:26', 'UTC');

        $this->assertSame('9:45 PM', DisplayTimezone::format($at, 'g:i A'));
    }
}
