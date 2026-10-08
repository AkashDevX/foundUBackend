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

    public function test_day_schedule_keeps_multiple_periods_not_available_and_overnight(): void
    {
        $raw = [
            'Mon' => [
                'status' => 'available',
                'periods' => [
                    ['start' => '09:00', 'end' => '13:00'],
                    ['start' => '18:00', 'end' => '22:00'],
                ],
            ],
            'Tue' => ['status' => 'unavailable', 'periods' => []],
            'Wed' => [
                'status' => 'available',
                'periods' => [
                    ['start' => '22:00', 'end' => '06:00'],
                ],
            ],
        ];

        $schedule = AdminWeeklyAvailability::dayScheduleState($raw);

        $this->assertTrue($schedule['mon']['available']);
        $this->assertSame('09:00', $schedule['mon']['periods'][0]['start']);
        $this->assertSame('22:00', $schedule['mon']['periods'][1]['end']);
        $this->assertFalse($schedule['tue']['available']);
        $this->assertSame([], $schedule['tue']['periods']);
        $this->assertTrue(AdminWeeklyAvailability::isOvernight('22:00', '06:00'));
        $this->assertStringContainsString('Mon: 09:00–13:00, 18:00–22:00', (string) AdminWeeklyAvailability::summaryTextFromStored($raw));
        $this->assertStringContainsString('Tue: Not available', (string) AdminWeeklyAvailability::summaryTextFromStored($raw));
        $this->assertStringContainsString('Wed: 22:00–06:00 (overnight)', (string) AdminWeeklyAvailability::summaryTextFromStored($raw));
    }

    public function test_legacy_morning_evening_lists_become_day_periods(): void
    {
        $schedule = AdminWeeklyAvailability::dayScheduleState([
            'Mon' => ['morning', 'evening'],
            'Tue' => [],
        ]);

        $this->assertSame([
            ['start' => '06:00', 'end' => '11:00'],
            ['start' => '17:00', 'end' => '22:00'],
        ], $schedule['mon']['periods']);
        $this->assertFalse($schedule['tue']['available']);
    }
}
