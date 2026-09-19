<?php

namespace Tests\Unit;

use App\Models\EmployeeScheduleShift;
use App\Support\TimeClockScheduledShift;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TimeClockScheduledShiftTest extends TestCase
{
    public function test_pick_best_for_moment_prefers_shift_window_containing_now(): void
    {
        $morning = $this->shift(1, '09:00', '12:00');
        $afternoon = $this->shift(2, '13:00', '17:00');

        $picked = TimeClockScheduledShift::pickBestForMoment(
            new Collection([$morning, $afternoon]),
            Carbon::parse('2026-06-30 14:15:00', 'UTC'),
        );

        $this->assertSame(2, $picked?->id);
    }

    public function test_pick_best_for_moment_prefers_upcoming_shift_between_blocks(): void
    {
        $morning = $this->shift(1, '09:00', '12:00');
        $afternoon = $this->shift(2, '13:00', '17:00');

        $picked = TimeClockScheduledShift::pickBestForMoment(
            new Collection([$morning, $afternoon]),
            Carbon::parse('2026-06-30 12:30:00', 'UTC'),
        );

        $this->assertSame(2, $picked?->id);
    }

    public function test_pick_best_for_moment_prefers_morning_before_first_start(): void
    {
        $morning = $this->shift(1, '09:00', '12:00');
        $afternoon = $this->shift(2, '13:00', '17:00');

        $picked = TimeClockScheduledShift::pickBestForMoment(
            new Collection([$morning, $afternoon]),
            Carbon::parse('2026-06-30 08:40:00', 'UTC'),
        );

        $this->assertSame(1, $picked?->id);
    }

    public function test_pick_best_for_moment_handles_overnight_window(): void
    {
        $day = $this->shift(1, '09:00', '17:00');
        $overnight = $this->shift(2, '22:00', '06:00');

        $picked = TimeClockScheduledShift::pickBestForMoment(
            new Collection([$day, $overnight]),
            Carbon::parse('2026-06-30 23:15:00', 'UTC'),
        );

        $this->assertSame(2, $picked?->id);
    }

    private function shift(int $id, string $start, string $end): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => $start,
            'end_time' => $end,
        ]);
        $shift->id = $id;

        return $shift;
    }
}
