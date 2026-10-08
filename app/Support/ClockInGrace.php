<?php

namespace App\Support;

use App\Models\EmployeeScheduleShift;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Clock-in window around a shift start.
 *
 * A 20-minute grace period on a 9:00 AM shift allows punches from 8:40 AM
 * through 9:20 AM. Outside that window the organization either blocks the
 * punch or requires an admin to clear an exception first.
 */
final class ClockInGrace
{
    public const POLICY_PREVENT = 'prevent';

    public const POLICY_EXCEPTION = 'exception';

    public const KIND_EARLY = 'early';

    public const KIND_LATE = 'late';

    public const CLEARANCE_PENDING = 'pending';

    public const CLEARANCE_CLEARED = 'cleared';

    public const ISSUE_TOO_EARLY = 'clock_in_too_early';

    public const ISSUE_TOO_LATE = 'clock_in_too_late';

    public const ISSUE_EXCEPTION_PENDING = 'clock_in_exception_pending';

    public static function clampMinutes(int $minutes): int
    {
        return max(0, min($minutes, 180));
    }

    public static function normalizePolicy(string $policy): string
    {
        return $policy === self::POLICY_PREVENT ? self::POLICY_PREVENT : self::POLICY_EXCEPTION;
    }

    /**
     * @return array{start: Carbon, earliest: Carbon, latest: Carbon}
     */
    public static function bounds(CarbonInterface $shiftStart, int $graceMinutes): array
    {
        $start = $shiftStart->copy()->seconds(0);
        $graceMinutes = self::clampMinutes($graceMinutes);

        return [
            'start' => $start,
            'earliest' => $start->copy()->subMinutes($graceMinutes),
            'latest' => $start->copy()->addMinutes($graceMinutes),
        ];
    }

    public static function deviation(CarbonInterface $now, CarbonInterface $shiftStart, int $graceMinutes): ?string
    {
        $bounds = self::bounds($shiftStart, $graceMinutes);
        $moment = $now->copy()->timezone($bounds['start']->timezone)->seconds(0);

        if ($moment->lt($bounds['earliest'])) {
            return self::KIND_EARLY;
        }

        if ($moment->gt($bounds['latest'])) {
            return self::KIND_LATE;
        }

        return null;
    }

    /**
     * @return array{blocks: bool, issue: string|null, record_exception: bool}
     */
    public static function decision(?string $deviation, string $policy, ?string $clearance): array
    {
        if ($deviation === null || $clearance === self::CLEARANCE_CLEARED) {
            return [
                'blocks' => false,
                'issue' => null,
                'record_exception' => false,
            ];
        }

        if (self::normalizePolicy($policy) === self::POLICY_PREVENT) {
            return [
                'blocks' => true,
                'issue' => $deviation === self::KIND_EARLY ? self::ISSUE_TOO_EARLY : self::ISSUE_TOO_LATE,
                'record_exception' => false,
            ];
        }

        return [
            'blocks' => true,
            'issue' => self::ISSUE_EXCEPTION_PENDING,
            'record_exception' => true,
        ];
    }

    public static function blockedMessage(
        string $issue,
        ?string $deviation,
        int $graceMinutes,
        CarbonInterface $shiftStart,
        CarbonInterface $earliest,
        CarbonInterface $latest,
    ): string {
        $startLabel = $shiftStart->format('g:i A');
        $earliestLabel = $earliest->format('g:i A');
        $latestLabel = $latest->format('g:i A');
        $graceMinutes = self::clampMinutes($graceMinutes);

        if ($issue === self::ISSUE_TOO_EARLY) {
            return "Clock-in opens at {$earliestLabel}. Your shift starts at {$startLabel} ({$graceMinutes}-minute grace period).";
        }

        if ($issue === self::ISSUE_TOO_LATE) {
            return "The clock-in window closed at {$latestLabel}. Your shift started at {$startLabel}. Contact your administrator.";
        }

        if ($deviation === self::KIND_EARLY) {
            return "You're early for this shift. Clock-in opens at {$earliestLabel}, or an administrator can clear the exception so you can clock in now.";
        }

        return "You're outside the {$graceMinutes}-minute clock-in window ({$earliestLabel}–{$latestLabel}). An administrator must clear the exception before you can clock in.";
    }

    public static function shiftStart(EmployeeScheduleShift $shift, ?CarbonInterface $fallbackDate = null): Carbon
    {
        $date = $shift->scheduled_date?->toDateString()
            ?? ($fallbackDate ?? DisplayTimezone::now())->toDateString();

        return self::shiftStartAt($date, self::storedTimeToHm($shift->start_time));
    }

    public static function shiftStartAt(string $date, string $startHm, ?string $timezone = null): Carbon
    {
        $timezone ??= DisplayTimezone::name();

        return Carbon::parse($date.' '.self::storedTimeToHm($startHm), $timezone)->seconds(0);
    }

    /**
     * @return array{start: string, earliest: string, latest: string}
     */
    public static function exampleLabels(int $graceMinutes): array
    {
        $bounds = self::bounds(Carbon::parse('2026-01-01 09:00:00', 'UTC'), $graceMinutes);

        return [
            'start' => '9:00 AM',
            'earliest' => $bounds['earliest']->format('g:i A'),
            'latest' => $bounds['latest']->format('g:i A'),
        ];
    }

    public static function storedTimeToHm(mixed $value, string $default = '09:00'): string
    {
        if ($value instanceof CarbonInterface) {
            return $value->format('H:i');
        }

        if (is_string($value) && preg_match('/^(\d{1,2}):(\d{2})/', $value, $matches)) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return $default;
    }

    public static function minutesOutside(CarbonInterface $now, CarbonInterface $boundary): int
    {
        $moment = $now->copy()->timezone($boundary->timezone)->seconds(0);
        $edge = $boundary->copy()->seconds(0);

        return (int) abs($moment->diffInMinutes($edge, false));
    }
}
