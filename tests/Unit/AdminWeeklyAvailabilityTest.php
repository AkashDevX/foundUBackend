<?php

namespace Tests\Unit;

use App\Support\AdminWeeklyAvailability;
use PHPUnit\Framework\TestCase;

class AdminWeeklyAvailabilityTest extends TestCase
{
    public function test_mobile_json_map_parses_period_lists(): void
    {
        $raw = [
            'Mon' => ['morning'],
            'Tue' => ['morning', 'evening'],
            'Wed' => [],
            'Thu' => ['evening'],
            'Fri' => [],
            'Sat' => [],
            'Sun' => [],
        ];

        $grid = AdminWeeklyAvailability::mobileGridState($raw);

        $this->assertTrue($grid['mon']['morning']);
        $this->assertFalse($grid['mon']['evening']);
        $this->assertTrue($grid['tue']['morning']);
        $this->assertTrue($grid['tue']['evening']);
        $this->assertFalse($grid['wed']['morning']);
        $this->assertTrue($grid['thu']['evening']);
    }

    public function test_mobile_summary_parses_colon_format(): void
    {
        $grid = AdminWeeklyAvailability::mobileGridStateForEmployee(
            null,
            'Mon: Morning, Evening · Thu: Evening'
        );

        $this->assertTrue($grid['mon']['morning']);
        $this->assertTrue($grid['mon']['evening']);
        $this->assertTrue($grid['thu']['evening']);
        $this->assertFalse($grid['fri']['morning']);
    }

    public function test_mobile_map_does_not_use_calendar_fallback_for_empty_slots(): void
    {
        $raw = [
            'Mon' => ['morning'],
            'Tue' => [],
            'Wed' => [],
            'Thu' => [],
            'Fri' => [],
            'Sat' => [],
            'Sun' => [],
        ];

        $grid = AdminWeeklyAvailability::mobileGridStateForEmployee($raw, null);

        $this->assertTrue($grid['mon']['morning']);
        $this->assertFalse($grid['mon']['evening']);
        $this->assertFalse($grid['tue']['morning']);
        $this->assertFalse($grid['tue']['evening']);
    }
}
