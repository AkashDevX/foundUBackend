<?php

namespace Tests\Unit;

use App\Support\BreakWindow;
use App\Support\DisplayTimezone;
use Carbon\Carbon;
use Tests\TestCase;

class BreakWindowTest extends TestCase
{
    public function test_six_am_shift_opens_the_break_between_the_fourth_and_sixth_hour(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 14:00:00', DisplayTimezone::name());
        $window = BreakWindow::assess($start, $end, $this->at('07:30'), $this->defaults(), false, false);

        $this->assertNotNull($window);
        $this->assertTrue($window['required']);
        $this->assertSame('10:00 AM', $window['opens_label']);
        $this->assertSame('12:00 PM', $window['closes_label']);
        $this->assertSame(
            'Please take your break between 10:00 AM and 12:00 PM.',
            $window['message'],
        );
        $this->assertSame(BreakWindow::PHASE_UPCOMING, $window['phase']);
        $this->assertFalse($window['within_window']);
        $this->assertFalse(BreakWindow::allowsBreakStart($window));
    }

    public function test_break_is_allowed_only_inside_the_window(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 14:00:00', DisplayTimezone::name());

        $before = BreakWindow::assess($start, $end, $this->at('09:59'), $this->defaults(), false, false);
        $this->assertSame(BreakWindow::PHASE_APPROACHING, $before['phase']);
        $this->assertFalse(BreakWindow::allowsBreakStart($before));

        $open = BreakWindow::assess($start, $end, $this->at('10:00'), $this->defaults(), false, false);
        $this->assertSame(BreakWindow::PHASE_OPEN, $open['phase']);
        $this->assertTrue(BreakWindow::allowsBreakStart($open));
        $this->assertNull($open['block_message']);

        $stillOpen = BreakWindow::assess($start, $end, $this->at('12:00'), $this->defaults(), false, false);
        $this->assertTrue($stillOpen['within_window']);

        $closed = BreakWindow::assess($start, $end, $this->at('12:01'), $this->defaults(), false, false);
        $this->assertSame(BreakWindow::PHASE_CLOSED, $closed['phase']);
        $this->assertFalse(BreakWindow::allowsBreakStart($closed));
        $this->assertStringContainsString('12:00 PM', (string) $closed['block_message']);
    }

    public function test_short_shifts_do_not_require_a_meal_break(): void
    {
        $start = Carbon::parse('2026-06-16 09:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 14:00:00', DisplayTimezone::name());

        $this->assertNull(BreakWindow::assess($start, $end, $this->at('11:00'), $this->defaults(), false, false));
    }

    public function test_window_close_is_pulled_back_to_the_shift_end(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 11:30:00', DisplayTimezone::name());
        $window = BreakWindow::assess($start, $end, $this->at('10:15'), $this->defaults(), false, false);

        $this->assertSame('10:00 AM', $window['opens_label']);
        $this->assertSame('11:30 AM', $window['closes_label']);
        $this->assertSame(
            'Please take your break between 10:00 AM and 11:30 AM.',
            $window['message'],
        );
    }

    public function test_overnight_shift_places_the_window_after_midnight(): void
    {
        $start = Carbon::parse('2026-06-16 22:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-17 06:00:00', DisplayTimezone::name());
        $window = BreakWindow::assess($start, $end, $this->at('02:30', '2026-06-17'), $this->defaults(), false, false);

        $this->assertSame('2:00 AM', $window['opens_label']);
        $this->assertSame('4:00 AM', $window['closes_label']);
        $this->assertSame(BreakWindow::PHASE_OPEN, $window['phase']);
    }

    public function test_a_started_break_is_marked_taken_and_an_open_break_stays_on_break(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 14:00:00', DisplayTimezone::name());

        $taken = BreakWindow::assess($start, $end, $this->at('11:00'), $this->defaults(), false, true);
        $this->assertSame(BreakWindow::PHASE_TAKEN, $taken['phase']);
        $this->assertTrue($taken['break_taken']);
        $this->assertTrue(BreakWindow::allowsBreakStart($taken));

        $onBreak = BreakWindow::assess($start, $end, $this->at('11:00'), $this->defaults(), true, true);
        $this->assertSame(BreakWindow::PHASE_ON_BREAK, $onBreak['phase']);
    }

    public function test_saved_hours_replace_the_default_fourth_to_sixth_hour(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $end = Carbon::parse('2026-06-16 15:00:00', DisplayTimezone::name());
        $settings = [
            'enabled' => true,
            'start_minutes' => 180,
            'end_minutes' => 420,
            'required_after_minutes' => 480,
            'reminder_lead_minutes' => 30,
        ];

        $nineHourShift = BreakWindow::assess($start, $end, $this->at('08:30'), $settings, false, false);
        $this->assertSame('9:00 AM', $nineHourShift['opens_label']);
        $this->assertSame('1:00 PM', $nineHourShift['closes_label']);
        $this->assertSame(30, $nineHourShift['reminder_lead_minutes']);
        $this->assertSame(BreakWindow::PHASE_APPROACHING, $nineHourShift['phase']);
        $this->assertFalse(BreakWindow::allowsBreakStart($nineHourShift));

        $open = BreakWindow::assess($start, $end, $this->at('09:00'), $settings, false, false);
        $this->assertTrue(BreakWindow::allowsBreakStart($open));

        $after = BreakWindow::assess($start, $end, $this->at('13:01'), $settings, false, false);
        $this->assertFalse(BreakWindow::allowsBreakStart($after));

        $eightHourShift = BreakWindow::assess(
            $start,
            Carbon::parse('2026-06-16 14:00:00', DisplayTimezone::name()),
            $this->at('10:00'),
            $settings,
            false,
            false,
        );
        $this->assertNull($eightHourShift);
        $this->assertTrue(BreakWindow::allowsBreakStart($eightHourShift));
    }

    public function test_disabled_rule_does_not_restrict_the_break(): void
    {
        $start = Carbon::parse('2026-06-16 06:00:00', DisplayTimezone::name());
        $settings = $this->defaults();
        $settings['enabled'] = false;

        $this->assertNull(BreakWindow::assess($start, $start->copy()->addHours(8), $this->at('07:00'), $settings, false, false));
        $this->assertTrue(BreakWindow::allowsBreakStart(null));
    }

    /**
     * @return array{enabled: bool, start_minutes: int, end_minutes: int, required_after_minutes: int, reminder_lead_minutes: int}
     */
    private function defaults(): array
    {
        return [
            'enabled' => true,
            'start_minutes' => 240,
            'end_minutes' => 360,
            'required_after_minutes' => 300,
            'reminder_lead_minutes' => 15,
        ];
    }

    private function at(string $time, string $date = '2026-06-16'): Carbon
    {
        return Carbon::parse($date.' '.$time, DisplayTimezone::name());
    }
}
