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

    public function test_home_drops_a_finished_shift_and_keeps_its_work_location(): void
    {
        $morning = $this->shift(1, '09:00', '12:00', 10);
        $afternoon = $this->shift(2, '13:00', '17:00', 20);

        $remaining = TimeClockScheduledShift::withoutFinishedIds(
            new Collection([$morning, $afternoon]),
            [1],
        );

        $this->assertCount(1, $remaining);
        $this->assertSame(2, $remaining->first()?->id);
        $this->assertSame(20, $remaining->first()?->work_location_id);
    }

    public function test_clock_in_target_stays_on_the_shift_whose_grace_window_is_open(): void
    {
        $morning = $this->shift(1, '09:00', '12:00', 10);
        $afternoon = $this->shift(2, '13:00', '17:00', 20);
        $shifts = new Collection([$morning, $afternoon]);

        $beforeMorning = TimeClockScheduledShift::selectClockInTarget(
            $shifts,
            Carbon::parse('2026-06-30 08:40:00', 'UTC'),
            20,
        );
        $this->assertSame(1, $beforeMorning?->id);
        $this->assertSame(10, $beforeMorning?->work_location_id);

        $duringMorning = TimeClockScheduledShift::selectClockInTarget(
            $shifts,
            Carbon::parse('2026-06-30 09:00:00', 'UTC'),
            20,
        );
        $this->assertSame(1, $duringMorning?->id);

        $betweenShifts = TimeClockScheduledShift::selectClockInTarget(
            $shifts,
            Carbon::parse('2026-06-30 12:30:00', 'UTC'),
            20,
        );
        $this->assertSame(2, $betweenShifts?->id);
        $this->assertSame(20, $betweenShifts?->work_location_id);

        $duringAfternoon = TimeClockScheduledShift::selectClockInTarget(
            TimeClockScheduledShift::withoutFinishedIds($shifts, [1]),
            Carbon::parse('2026-06-30 12:50:00', 'UTC'),
            20,
        );
        $this->assertSame(2, $duringAfternoon?->id);
    }

    public function test_clock_in_target_stays_on_the_shift_that_is_still_running(): void
    {
        $morning = $this->shift(1, '09:00', '17:00', 10);
        $evening = $this->shift(2, '18:00', '20:00', 20);
        $night = $this->shift(3, '21:00', '23:00', 30);
        $shifts = new Collection([$morning, $evening, $night]);

        $duringEvening = TimeClockScheduledShift::selectClockInTarget(
            $shifts,
            Carbon::parse('2026-06-30 19:42:00', 'UTC'),
            20,
        );

        $this->assertSame(2, $duringEvening?->id);
        $this->assertSame(20, $duringEvening?->work_location_id);
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

    private function shift(int $id, string $start, string $end, ?int $workLocationId = null): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => $start,
            'end_time' => $end,
            'work_location_id' => $workLocationId,
        ]);
        $shift->id = $id;

        return $shift;
    }
}
