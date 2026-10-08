<?php

namespace App\Support;

use App\Models\TimeClockSetting;
use Illuminate\Support\Facades\Schema;

final class ClockInGraceSettings
{
    /** @var array<string, array{grace_minutes: int, outside_policy: string, persisted: bool, ready: bool}> */
    private static array $cache = [];

    /**
     * @return array{grace_minutes: int, outside_policy: string, persisted: bool, ready: bool}
     */
    public static function current(?string $connection = null): array
    {
        $connection ??= (string) config('database.default');
        if (isset(self::$cache[$connection])) {
            return self::$cache[$connection];
        }

        $fallback = [
            'grace_minutes' => ClockInGrace::clampMinutes((int) config('time_clock.clock_in_grace_minutes', 20)),
            'outside_policy' => ClockInGrace::normalizePolicy((string) config('time_clock.clock_in_outside_grace_policy', ClockInGrace::POLICY_EXCEPTION)),
            'persisted' => false,
            'ready' => false,
        ];

        if (! Schema::connection($connection)->hasTable('time_clock_settings')
            || ! Schema::connection($connection)->hasTable('clock_in_exceptions')) {
            return self::$cache[$connection] = $fallback;
        }

        $fallback['ready'] = true;
        $row = TimeClockSetting::on($connection)->orderBy('id')->first();
        if (! $row instanceof TimeClockSetting) {
            return self::$cache[$connection] = $fallback;
        }

        return self::$cache[$connection] = [
            'grace_minutes' => ClockInGrace::clampMinutes((int) $row->grace_minutes),
            'outside_policy' => ClockInGrace::normalizePolicy((string) $row->outside_policy),
            'persisted' => true,
            'ready' => true,
        ];
    }

    public static function save(string $connection, int $graceMinutes, string $policy): TimeClockSetting
    {
        $row = TimeClockSetting::on($connection)->orderBy('id')->first() ?? new TimeClockSetting;
        $row->setConnection($connection);
        $row->grace_minutes = ClockInGrace::clampMinutes($graceMinutes);
        $row->outside_policy = ClockInGrace::normalizePolicy($policy);
        $row->save();

        self::forget();

        return $row;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }
}
