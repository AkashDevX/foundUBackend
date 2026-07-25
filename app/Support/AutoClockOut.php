<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Pure decision logic for the server-side auto clock-out safety net.
 *
 * Kept free of Eloquent/DB so it can be unit tested in isolation: given an
 * already-resolved shift window (or none) it decides *whether* and *when* an
 * open session should be closed.
 */
final class AutoClockOut
{
    /**
     * Decide when an open session should be closed, or null if not yet.
     *
     * Primary rule: the scheduled shift end (for the day the shift started) plus
     * a grace period. Fallback: a hard max-session-hours cap so a session never
     * stays open forever when no shift end can be resolved.
     *
     * @param  array{start_time?: string, end_time?: string}|null  $shiftTimes  Resolved "HH:MM" times, or null.
     * @return array{0: CarbonInterface|null, 1: string}  [closeAt in $tz, reason] — closeAt is null when nothing to do.
     */
    public static function resolveCloseAt(
        ?array $shiftTimes,
        CarbonInterface $clockInUtc,
        CarbonInterface $now,
        string $tz,
        int $graceMinutes,
        int $maxHours,
    ): array {
        $graceMinutes = max(0, $graceMinutes);
        $maxHours = max(1, $maxHours);
        $clockInLocal = $clockInUtc->copy()->timezone($tz);

        $startHm = is_array($shiftTimes) ? (string) ($shiftTimes['start_time'] ?? '') : '';
        $endHm = is_array($shiftTimes) ? (string) ($shiftTimes['end_time'] ?? '') : '';

        if ($startHm !== '' && $endHm !== '') {
            $start = Carbon::parse($clockInLocal->toDateString().' '.$startHm, $tz);
            $end = Carbon::parse($clockInLocal->toDateString().' '.$endHm, $tz);
            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay(); // Overnight shift crosses midnight.
            }

            if ($now->greaterThanOrEqualTo($end->copy()->addMinutes($graceMinutes))) {
                return [$end, 'shift ended'];
            }
        }

        $maxEnd = $clockInLocal->copy()->addHours($maxHours);
        if ($now->greaterThanOrEqualTo($maxEnd)) {
            return [$maxEnd, "open over {$maxHours}h"];
        }

        return [null, ''];
    }
}
