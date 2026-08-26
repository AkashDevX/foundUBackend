<?php

namespace Tests\Unit;

use App\Models\Shift;
use App\Support\WorkforceShifts;
use Tests\TestCase;

class WorkforceShiftsTest extends TestCase
{
    public function test_option_payload_formats_shift_for_the_schedule_dropdown(): void
    {
        $shift = new Shift([
            'name' => 'Late retail',
            'start_time' => '16:00:00',
            'end_time' => '00:00:00',
        ]);
        $shift->id = 42;

        $payload = WorkforceShifts::optionPayload($shift);

        $this->assertSame(42, $payload['id']);
        $this->assertSame('Late retail', $payload['name']);
        $this->assertSame('16:00', $payload['start_time']);
        $this->assertSame('00:00', $payload['end_time']);
        $this->assertSame('Late retail · 4:00 PM – 12:00 AM', $payload['option_label']);
    }

    public function test_normalize_days_keeps_valid_unique_weekdays(): void
    {
        $this->assertNull(WorkforceShifts::normalizeDays(null));
        $this->assertNull(WorkforceShifts::normalizeDays([]));
        $this->assertSame(
            ['mon', 'wed', 'fri'],
            WorkforceShifts::normalizeDays(['Mon', 'wed', 'fri', 'monday', 'wed'])
        );
    }

    public function test_catalog_entry_includes_days_breaks_and_usage(): void
    {
        $shift = new Shift([
            'name' => 'Morning',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            'shift_days' => ['mon', 'tue'],
            'breaks' => [
                ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
            ],
            'notes' => 'Front desk',
        ]);
        $shift->id = 7;

        $entry = WorkforceShifts::catalogEntry($shift, 3, 12);

        $this->assertSame(7, $entry['id']);
        $this->assertSame(['mon', 'tue'], $entry['shift_days']);
        $this->assertSame([['label' => 'Lunch', 'minutes' => 30, 'paid' => false]], $entry['breaks']);
        $this->assertSame('Front desk', $entry['notes']);
        $this->assertSame(3, $entry['employee_count']);
        $this->assertSame(12, $entry['schedule_count']);
    }
}
