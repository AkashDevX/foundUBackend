<?php

namespace App\Support;

use App\Models\Employee;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Meal-break window measured from the scheduled shift start.
 *
 * Australian workplace rules (including awards such as the Cleaning Services
 * Award) generally require an unpaid meal break once a shift runs longer than
 * 5 hours. The default here keeps that break between the 4th and 6th hour of
 * the shift, so it cannot be taken at the start or the end of the shift.
 *
 * A 6:00 AM shift with the defaults tells the employee:
 * "Please take your break between 10:00 AM and 12:00 PM."
 */
final class BreakWindow
{
    public const PHASE_UPCOMING = 'upcoming';

    public const PHASE_APPROACHING = 'approaching';

    public const PHASE_OPEN = 'open';

    public const PHASE_ON_BREAK = 'on_break';

    public const PHASE_TAKEN = 'taken';

    public const PHASE_CLOSED = 'closed';

    public const ISSUE_OUTSIDE = 'break_outside_window';

    /**
     * @return array{
     *     enabled: bool,
     *     start_minutes: int,
     *     end_minutes: int,
     *     required_after_minutes: int,
     *     reminder_lead_minutes: int
     * }
     */
    public static function defaults(): array
    {
        $start = self::clampStartMinutes((int) config('time_clock.break_window.start_minutes', 240));
        $end = self::clampEndMinutes((int) config('time_clock.break_window.end_minutes', 360), $start);

        return [
            'enabled' => (bool) config('time_clock.break_window.enabled', true),
            'start_minutes' => $start,
            'end_minutes' => $end,
            'required_after_minutes' => self::clampRequiredAfterMinutes((int) config('time_clock.break_window.required_after_minutes', 300)),
            'reminder_lead_minutes' => self::clampLeadMinutes((int) config('time_clock.break_window.reminder_lead_minutes', 15)),
        ];
    }

    public static function clampStartMinutes(int $minutes): int
    {
        return max(60, min($minutes, 12 * 60));
    }

    public static function clampEndMinutes(int $minutes, int $startMinutes): int
    {
        $startMinutes = self::clampStartMinutes($startMinutes);
        $minutes = max($startMinutes + 30, min($minutes, 16 * 60));

        return $minutes;
    }

    public static function clampRequiredAfterMinutes(int $minutes): int
    {
        return max(0, min($minutes, 16 * 60));
    }

    public static function clampLeadMinutes(int $minutes): int
    {
        return max(0, min($minutes, 120));
    }

    public static function hoursToMinutes(float $hours): int
    {
        return (int) round($hours * 60);
    }

    public static function minutesToHoursInput(int $minutes): string
    {
        $hours = $minutes / 60;
        if (abs($hours - round($hours)) < 0.001) {
            return (string) (int) round($hours);
        }

        return rtrim(rtrim(number_format($hours, 2, '.', ''), '0'), '.');
    }

    /**
     * @param  array{enabled?: bool, start_minutes?: int, end_minutes?: int, required_after_minutes?: int, reminder_lead_minutes?: int}  $settings
     * @return array<string, mixed>|null
     */
    public static function assess(
        CarbonInterface $shiftStart,
        ?CarbonInterface $shiftEnd,
        CarbonInterface $now,
        array $settings,
        bool $onBreak,
        bool $breakStarted,
    ): ?array {
        $enabled = (bool) ($settings['enabled'] ?? true);
        if (! $enabled) {
            return null;
        }

        $start = $shiftStart->copy()->timezone(DisplayTimezone::name())->seconds(0);
        $end = $shiftEnd?->copy()->timezone(DisplayTimezone::name())->seconds(0);
        if ($end !== null && $end->lessThanOrEqualTo($start)) {
            $end = $end->copy()->addDay();
        }

        $startMinutes = self::clampStartMinutes((int) ($settings['start_minutes'] ?? 240));
        $endMinutes = self::clampEndMinutes((int) ($settings['end_minutes'] ?? 360), $startMinutes);
        $requiredAfter = self::clampRequiredAfterMinutes((int) ($settings['required_after_minutes'] ?? 300));
        $lead = self::clampLeadMinutes((int) ($settings['reminder_lead_minutes'] ?? 15));

        if ($end !== null) {
            $duration = (int) $start->diffInMinutes($end);
            if ($duration <= $requiredAfter) {
                return null;
            }
        }

        $opens = $start->copy()->addMinutes($startMinutes);
        $closes = $start->copy()->addMinutes($endMinutes);
        if ($end !== null && $opens->greaterThanOrEqualTo($end)) {
            return null;
        }
        if ($end !== null && $end->greaterThan($opens) && $end->lessThan($closes)) {
            $closes = $end->copy();
        }

        $moment = $now->copy()->timezone(DisplayTimezone::name())->seconds(0);
        $within = $moment->greaterThanOrEqualTo($opens) && $moment->lessThanOrEqualTo($closes);
        $opensLabel = $opens->format('g:i A');
        $closesLabel = $closes->format('g:i A');
        $message = self::employeeMessage($opensLabel, $closesLabel);

        if ($onBreak) {
            $phase = self::PHASE_ON_BREAK;
        } elseif ($breakStarted) {
            $phase = self::PHASE_TAKEN;
        } elseif ($moment->lessThan($opens->copy()->subMinutes($lead))) {
            $phase = self::PHASE_UPCOMING;
        } elseif ($moment->lessThan($opens)) {
            $phase = self::PHASE_APPROACHING;
        } elseif ($within) {
            $phase = self::PHASE_OPEN;
        } else {
            $phase = self::PHASE_CLOSED;
        }

        $blockMessage = $within
            ? null
            : "You can only take your break between {$opensLabel} and {$closesLabel}.";

        return [
            'required' => true,
            'opens_at' => $opens->copy()->utc()->toIso8601String(),
            'closes_at' => $closes->copy()->utc()->toIso8601String(),
            'opens_label' => $opensLabel,
            'closes_label' => $closesLabel,
            'message' => $message,
            'phase' => $phase,
            'within_window' => $within,
            'break_taken' => $onBreak || $breakStarted,
            'reminder_lead_minutes' => $lead,
            'block_message' => $blockMessage,
        ];
    }

    public static function employeeMessage(string $opensLabel, string $closesLabel): string
    {
        return "Please take your break between {$opensLabel} and {$closesLabel}.";
    }

    /**
     * @param  array<string, mixed>|null  $window
     */
    public static function allowsBreakStart(?array $window): bool
    {
        if ($window === null || empty($window['required'])) {
            return true;
        }

        return ($window['within_window'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function forEmployee(Employee $employee, bool $onBreak, bool $breakStarted, ?CarbonInterface $now = null): ?array
    {
        $now = ($now ?? DisplayTimezone::now())->copy()->timezone(DisplayTimezone::name());
        $shift = self::shiftBounds($employee, $now);
        if ($shift === null) {
            return null;
        }

        $settings = BreakWindowSettings::current($employee->getConnection()->getName());

        return self::assess($shift['start'], $shift['end'], $now, $settings, $onBreak, $breakStarted);
    }

    public static function exampleSentence(int $startMinutes, int $endMinutes): string
    {
        $start = Carbon::parse('2026-01-01 06:00:00', DisplayTimezone::name());
        $window = self::assess(
            $start,
            $start->copy()->addHours(8),
            $start->copy()->addHours(3),
            [
                'enabled' => true,
                'start_minutes' => $startMinutes,
                'end_minutes' => $endMinutes,
                'required_after_minutes' => 300,
                'reminder_lead_minutes' => 15,
            ],
            false,
            false,
        );

        return is_array($window) ? (string) $window['message'] : self::employeeMessage('10:00 AM', '12:00 PM');
    }

    /**
     * @return array{start: Carbon, end: Carbon|null}|null
     */
    private static function shiftBounds(Employee $employee, CarbonInterface $now): ?array
    {
        $display = TimeClockScheduledShift::todayShiftForDisplay($employee, $now);
        if ($display === null) {
            return null;
        }

        $date = $now->copy()->timezone(DisplayTimezone::name())->toDateString();
        $start = ClockInGrace::shiftStartAt($date, (string) $display['start_time']);
        $endHm = trim((string) ($display['end_time'] ?? ''));
        if ($endHm === '') {
            return ['start' => $start, 'end' => null];
        }

        $end = ClockInGrace::shiftStartAt($date, $endHm);
        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return ['start' => $start, 'end' => $end];
    }
}
