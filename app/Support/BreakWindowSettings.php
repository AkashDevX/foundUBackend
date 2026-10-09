<?php

namespace App\Support;

use App\Models\TimeClockSetting;
use Illuminate\Support\Facades\Schema;

final class BreakWindowSettings
{
    /** @var array<string, array<string, mixed>> */
    private static array $cache = [];

    /**
     * @return array{
     *     enabled: bool,
     *     start_minutes: int,
     *     end_minutes: int,
     *     required_after_minutes: int,
     *     reminder_lead_minutes: int,
     *     persisted: bool,
     *     ready: bool
     * }
     */
    public static function current(?string $connection = null): array
    {
        $connection ??= (string) config('database.default');
        if (isset(self::$cache[$connection])) {
            /** @var array{enabled: bool, start_minutes: int, end_minutes: int, required_after_minutes: int, reminder_lead_minutes: int, persisted: bool, ready: bool} $cached */
            $cached = self::$cache[$connection];

            return $cached;
        }

        $fallback = self::fallback(false);
        if (! Schema::connection($connection)->hasTable('time_clock_settings')
            || ! Schema::connection($connection)->hasColumn('time_clock_settings', 'break_rule_enabled')) {
            return self::$cache[$connection] = $fallback;
        }

        $fallback['ready'] = true;
        $row = TimeClockSetting::on($connection)->orderBy('id')->first();
        if (! $row instanceof TimeClockSetting || $row->break_window_start_minutes === null) {
            return self::$cache[$connection] = $fallback;
        }

        $start = BreakWindow::clampStartMinutes((int) $row->break_window_start_minutes);
        $end = BreakWindow::clampEndMinutes((int) $row->break_window_end_minutes, $start);

        return self::$cache[$connection] = [
            'enabled' => (bool) $row->break_rule_enabled,
            'start_minutes' => $start,
            'end_minutes' => $end,
            'required_after_minutes' => BreakWindow::clampRequiredAfterMinutes((int) $row->break_required_after_minutes),
            'reminder_lead_minutes' => BreakWindow::clampLeadMinutes((int) $row->break_reminder_lead_minutes),
            'persisted' => true,
            'ready' => true,
        ];
    }

    public static function save(
        string $connection,
        bool $enabled,
        int $startMinutes,
        int $endMinutes,
        int $requiredAfterMinutes,
        int $reminderLeadMinutes,
    ): TimeClockSetting {
        $row = TimeClockSetting::on($connection)->orderBy('id')->first();
        if (! $row instanceof TimeClockSetting) {
            $row = new TimeClockSetting;
            $row->setConnection($connection);
            $row->grace_minutes = ClockInGrace::clampMinutes((int) config('time_clock.clock_in_grace_minutes', 20));
            $row->outside_policy = ClockInGrace::normalizePolicy((string) config('time_clock.clock_in_outside_grace_policy', ClockInGrace::POLICY_EXCEPTION));
        } else {
            $row->setConnection($connection);
        }

        $start = BreakWindow::clampStartMinutes($startMinutes);
        $row->break_rule_enabled = $enabled;
        $row->break_window_start_minutes = $start;
        $row->break_window_end_minutes = BreakWindow::clampEndMinutes($endMinutes, $start);
        $row->break_required_after_minutes = BreakWindow::clampRequiredAfterMinutes($requiredAfterMinutes);
        $row->break_reminder_lead_minutes = BreakWindow::clampLeadMinutes($reminderLeadMinutes);
        $row->save();

        self::forget();

        return $row;
    }

    public static function forget(): void
    {
        self::$cache = [];
    }

    /**
     * @return array{
     *     enabled: bool,
     *     start_minutes: int,
     *     end_minutes: int,
     *     required_after_minutes: int,
     *     reminder_lead_minutes: int,
     *     persisted: bool,
     *     ready: bool
     * }
     */
    private static function fallback(bool $ready): array
    {
        $defaults = BreakWindow::defaults();

        return [
            ...$defaults,
            'persisted' => false,
            'ready' => $ready,
        ];
    }
}
