<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Brisbane business timezone for all user-facing dates and times.
 * Australia/Brisbane is AEST (UTC+10) all year and does not observe daylight saving.
 * Database timestamps remain UTC; convert at display boundaries.
 */
final class DisplayTimezone
{
    public static function name(): string
    {
        return (string) config('app.display_timezone', 'Australia/Brisbane');
    }

    public static function locale(): string
    {
        return (string) config('app.display_locale', 'en_AU');
    }

    public static function now(): Carbon
    {
        return now('UTC')->timezone(self::name());
    }

    public static function format(?CarbonInterface $at, string $format): string
    {
        if ($at === null) {
            return '—';
        }

        return $at->copy()->timezone(self::name())->format($format);
    }

    public static function formatDateTime(?CarbonInterface $at): string
    {
        return self::format($at, 'M j, Y g:i A');
    }

    public static function formatDate(?CarbonInterface $at): string
    {
        return self::format($at, 'M j, Y');
    }

    public static function label(): string
    {
        return self::now()->format('T');
    }
}
