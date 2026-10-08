<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Decode employee weekly_availability_json into admin calendar state and encode POST back to JSON.
 */
final class AdminWeeklyAvailability
{
    /** @var list<string> */
    public const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    public const MORNING_START = '06:00';

    public const MORNING_END = '11:00';

    public const EVENING_START = '17:00';

    public const EVENING_END = '22:00';

    /** @var array<string, string> */
    public const FULL_DAY_LABELS = [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ];

    /** @var array<string, string> */
    public const SHORT_DAY_LABELS = [
        'mon' => 'Mo',
        'tue' => 'Tu',
        'wed' => 'We',
        'thu' => 'Th',
        'fri' => 'Fr',
        'sat' => 'Sa',
        'sun' => 'Su',
    ];

    /** Day keys as stored by the React Native registration wizard (Step2WorkEligibility). */
    public const MOBILE_DAY_LABELS = [
        'mon' => 'Mon',
        'tue' => 'Tue',
        'wed' => 'Wed',
        'thu' => 'Thu',
        'fri' => 'Fri',
        'sat' => 'Sat',
        'sun' => 'Sun',
    ];

    /**
     * Calendar state for admin edit: JSON first, then fill gaps from free-text summary.
     *
     * @return array<string, array{on: bool, start: string, end: string}>
     */
    public static function calendarStateForEmployee(?array $raw, ?string $summary = null): array
    {
        $state = self::calendarState($raw);
        self::mergeSummaryIntoState($state, $summary);

        return $state;
    }

    /**
     * Mobile-style grid: morning / evening toggles per day (matches Workforce step 2 UI).
     *
     * @return array<string, array{morning: bool, evening: bool}>
     */
    public static function mobileGridStateForEmployee(?array $raw, ?string $summary = null): array
    {
        $grid = self::mobileGridState($raw);
        if (! self::mobileGridHasSelection($grid) && ! self::isMobileWeeklyDayMap($raw)) {
            $calendar = self::calendarStateForEmployee($raw, null);
            foreach (self::DAY_KEYS as $day) {
                if (! ($calendar[$day]['on'] ?? false)) {
                    continue;
                }
                $start = $calendar[$day]['start'] ?? self::MORNING_START;
                $end = $calendar[$day]['end'] ?? self::EVENING_END;
                if (self::slotOverlapsMorning($start, $end)) {
                    $grid[$day]['morning'] = true;
                }
                if (self::slotOverlapsEvening($start, $end)) {
                    $grid[$day]['evening'] = true;
                }
            }
        }
        if (! self::mobileGridHasSelection($grid)) {
            self::mergeSummaryIntoMobileGrid($grid, $summary);
        }

        return $grid;
    }

    /**
     * @return array<string, array{morning: bool, evening: bool}>
     */
    public static function mobileGridState(?array $raw): array
    {
        $grid = [];
        foreach (self::DAY_KEYS as $day) {
            $grid[$day] = ['morning' => false, 'evening' => false];
        }

        if ($raw === null || $raw === []) {
            return $grid;
        }

        if (array_is_list($raw)) {
            foreach ($raw as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $day = self::dayKeyFromBlock($block);
                if ($day === null) {
                    continue;
                }
                self::applyBlockToMobileGrid($grid, $day, $block);
            }

            return $grid;
        }

        if (self::isAssoc($raw)) {
            foreach ($raw as $key => $value) {
                $day = self::normalizeDaySlug((string) $key);
                if ($day === null) {
                    continue;
                }
                if (is_array($value)) {
                    if (array_is_list($value)) {
                        self::applyPeriodListToMobileGrid($grid, $day, $value);
                    } else {
                        self::applyBlockToMobileGrid($grid, $day, $value);
                    }
                } elseif (is_bool($value) && $value) {
                    $period = self::normalizePeriodSlug((string) $key);
                    if ($period === 'morning') {
                        foreach (self::DAY_KEYS as $d) {
                            $grid[$d]['morning'] = true;
                        }
                    } elseif ($period === 'evening') {
                        foreach (self::DAY_KEYS as $d) {
                            $grid[$d]['evening'] = true;
                        }
                    }
                }
            }
        }

        return $grid;
    }

    /**
     * @return array<string, array{on: bool, start: string, end: string}>
     */
    public static function calendarState(?array $raw): array
    {
        $state = [];
        foreach (self::DAY_KEYS as $d) {
            $state[$d] = ['on' => false, 'start' => '09:00', 'end' => '17:00'];
        }

        if ($raw === null || $raw === []) {
            return $state;
        }

        if (array_is_list($raw)) {
            foreach ($raw as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $day = self::dayKeyFromBlock($block);
                if ($day === null) {
                    continue;
                }
                $state[$day]['on'] = true;
                $slot = self::mergedSlotFromBlock($block);
                if ($slot !== null) {
                    $state[$day]['start'] = $slot['start'];
                    $state[$day]['end'] = $slot['end'];
                }
            }

            return $state;
        }

        if (self::isAssoc($raw)) {
            foreach ($raw as $key => $value) {
                $day = self::normalizeDaySlug((string) $key);
                if ($day === null) {
                    continue;
                }
                $state[$day]['on'] = true;
                if (is_string($value) && preg_match('/(\d{1,2}:\d{2})\s*[–\-\s]+\s*(\d{1,2}:\d{2})/u', $value, $m)) {
                    $state[$day]['start'] = self::normalizeTimeString($m[1]) ?? '09:00';
                    $state[$day]['end'] = self::normalizeTimeString($m[2]) ?? '17:00';
                } elseif (is_array($value)) {
                    $slot = self::mergedSlotFromBlock(['slots' => is_array($value) && array_is_list($value) ? $value : [$value]]);
                    if ($slot !== null) {
                        $state[$day]['start'] = $slot['start'];
                        $state[$day]['end'] = $slot['end'];
                    }
                } elseif (is_bool($value) && $value) {
                    $periodSlot = self::periodDefaults((string) $key);
                    if ($periodSlot !== null) {
                        $state[$day]['start'] = $periodSlot['start'];
                        $state[$day]['end'] = $periodSlot['end'];
                    }
                }
            }
        }

        return $state;
    }

    /**
     * @param  array<string, array{on: bool, start: string, end: string}>  $state
     */
    private static function mergeSummaryIntoState(array &$state, ?string $summary): void
    {
        if ($summary === null || trim($summary) === '') {
            return;
        }

        $text = str_replace(["\r\n", "\r"], "\n", trim($summary));
        $chunks = preg_split('/[\n,;]+/', $text) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }
            if (! preg_match('/\b(mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $chunk, $dayMatch)) {
                continue;
            }
            $day = self::normalizeDaySlug($dayMatch[1]);
            if ($day === null) {
                continue;
            }

            $starts = [];
            $ends = [];

            if (preg_match_all('/(\d{1,2}:\d{2})\s*[–\-—to]+\s*(\d{1,2}:\d{2})/iu', $chunk, $ranges, PREG_SET_ORDER)) {
                foreach ($ranges as $range) {
                    $s = self::normalizeTimeString($range[1]);
                    $e = self::normalizeTimeString($range[2]);
                    if ($s !== null && $e !== null) {
                        $starts[] = $s;
                        $ends[] = $e;
                    }
                }
            }

            if ($starts === [] && preg_match_all('/(\d{1,2})(?::(\d{2}))?\s*(am|pm)\s*[–\-—to]+\s*(\d{1,2})(?::(\d{2}))?\s*(am|pm)/iu', $chunk, $ampm, PREG_SET_ORDER)) {
                foreach ($ampm as $m) {
                    $s = self::normalizeTimeString($m[1].':'.($m[2] ?: '00').' '.$m[3]);
                    $e = self::normalizeTimeString($m[4].':'.($m[5] ?: '00').' '.$m[6]);
                    if ($s !== null && $e !== null) {
                        $starts[] = $s;
                        $ends[] = $e;
                    }
                }
            }

            if ($starts === [] && preg_match('/\bmorning\b/i', $chunk)) {
                $period = self::periodDefaults('morning');
                if ($period !== null) {
                    $starts[] = $period['start'];
                    $ends[] = $period['end'];
                }
            }
            if ($starts === [] && preg_match('/\bafternoon\b/i', $chunk)) {
                $period = self::periodDefaults('afternoon');
                if ($period !== null) {
                    $starts[] = $period['start'];
                    $ends[] = $period['end'];
                }
            }
            if ($starts === [] && preg_match('/\bevening\b/i', $chunk)) {
                $period = self::periodDefaults('evening');
                if ($period !== null) {
                    $starts[] = $period['start'];
                    $ends[] = $period['end'];
                }
            }

            if ($starts === [] && preg_match('/\b(all\s*day|available|on)\b/i', $chunk)) {
                $starts[] = '09:00';
                $ends[] = '17:00';
            }

            if ($starts === []) {
                continue;
            }

            $state[$day]['on'] = true;
            $state[$day]['start'] = min($starts);
            $state[$day]['end'] = max($ends);
        }
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{start: string, end: string}|null
     */
    private static function mergedSlotFromBlock(array $block): ?array
    {
        $starts = [];
        $ends = [];

        foreach (['slots', 'times', 'ranges', 'hours', 'intervals', 'periods'] as $k) {
            if (empty($block[$k]) || ! is_array($block[$k])) {
                continue;
            }
            foreach ($block[$k] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $s = $slot['start'] ?? $slot['from'] ?? null;
                $e = $slot['end'] ?? $slot['to'] ?? null;
                if ($s !== null && $e !== null) {
                    $start = self::normalizeTimeString((string) $s);
                    $end = self::normalizeTimeString((string) $e);
                    if ($start !== null && $end !== null) {
                        $starts[] = $start;
                        $ends[] = $end;
                    }
                }
            }
        }

        foreach (['morning', 'afternoon', 'evening', 'am', 'pm'] as $period) {
            if (! array_key_exists($period, $block)) {
                continue;
            }
            $v = $block[$period];
            if (is_bool($v) && $v) {
                $defaults = self::periodDefaults($period);
                if ($defaults !== null) {
                    $starts[] = $defaults['start'];
                    $ends[] = $defaults['end'];
                }
            } elseif (is_string($v) || is_numeric($v)) {
                if (preg_match('/(\d{1,2}:\d{2})\s*[–\-\s]+\s*(\d{1,2}:\d{2})/u', (string) $v, $m)) {
                    $start = self::normalizeTimeString($m[1]);
                    $end = self::normalizeTimeString($m[2]);
                    if ($start !== null && $end !== null) {
                        $starts[] = $start;
                        $ends[] = $end;
                    }
                }
            }
        }

        if ($starts === []) {
            return self::firstSlot($block);
        }

        return ['start' => min($starts), 'end' => max($ends)];
    }

    /**
     * @return array{start: string, end: string}|null
     */
    private static function periodDefaults(string $period): ?array
    {
        $p = strtolower(preg_replace('/[^a-z]/', '', $period) ?? $period);

        return match ($p) {
            'morning', 'am' => ['start' => self::MORNING_START, 'end' => self::MORNING_END],
            'afternoon' => ['start' => '12:00', 'end' => '17:00'],
            'evening', 'pm' => ['start' => self::EVENING_START, 'end' => self::EVENING_END],
            default => null,
        };
    }

    public static function encodeFromRequest(Request $request): ?array
    {
        $posted = $request->input('availability');
        if (is_array($posted) && self::postedUsesDaySchedule($posted)) {
            return self::encodeDaySchedule($posted);
        }

        $map = [];
        $hasSelection = false;
        foreach (self::DAY_KEYS as $day) {
            $periods = [];
            if ($request->boolean("availability.$day.morning")) {
                $periods[] = 'morning';
            }
            if ($request->boolean("availability.$day.evening")) {
                $periods[] = 'evening';
            }
            if ($periods !== []) {
                $hasSelection = true;
            }
            $map[self::MOBILE_DAY_LABELS[$day] ?? ucfirst($day)] = $periods;
        }

        return $hasSelection ? $map : null;
    }

    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     */
    public static function summaryTextFromMobileGrid(array $grid): ?string
    {
        $parts = [];
        foreach (self::DAY_KEYS as $day) {
            $slotLabels = [];
            if ($grid[$day]['morning'] ?? false) {
                $slotLabels[] = 'Morning';
            }
            if ($grid[$day]['evening'] ?? false) {
                $slotLabels[] = 'Evening';
            }
            if ($slotLabels === []) {
                continue;
            }
            $parts[] = (self::MOBILE_DAY_LABELS[$day] ?? ucfirst($day)).': '.implode(', ', $slotLabels);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public static function mobileMorningRangeLabel(): string
    {
        return self::formatTime12(self::MORNING_START).' – '.self::formatTime12(self::MORNING_END);
    }

    public static function mobileEveningRangeLabel(): string
    {
        return self::formatTime12(self::EVENING_START).' – '.self::formatTime12(self::EVENING_END);
    }

    /**
     * Human-readable summary aligned with calendar day columns (Mon–Sun).
     *
     * @param  array<string, array{on: bool, start: string, end: string}>|null  $state
     */
    public static function formatTime12(string $hhmm): string
    {
        $normalized = self::normalizeTimeString($hhmm);
        if ($normalized === null) {
            return $hhmm;
        }

        try {
            return Carbon::createFromFormat('H:i', $normalized)->format('g:i A');
        } catch (\Throwable) {
            return $normalized;
        }
    }

    public static function periodLabel(string $start, string $end): string
    {
        $start = self::normalizeTimeString($start) ?? '09:00';
        $end = self::normalizeTimeString($end) ?? '17:00';

        return match ($start.'|'.$end) {
            self::MORNING_START.'|'.self::MORNING_END, '06:00|12:00' => 'Morning',
            '12:00|17:00' => 'Afternoon',
            self::EVENING_START.'|'.self::EVENING_END, '17:00|22:00' => 'Evening',
            '09:00|17:00' => 'Day shift',
            '08:00|16:00' => 'Day shift',
            default => self::formatTime12($start).' – '.self::formatTime12($end),
        };
    }

    public static function summaryTextFromCalendar(?array $state): ?string
    {
        if ($state === null || $state === []) {
            return null;
        }

        $labels = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
        $lines = [];
        foreach (self::DAY_KEYS as $day) {
            $on = $state[$day]['on'] ?? false;
            if (! $on) {
                continue;
            }
            $start = $state[$day]['start'] ?? '09:00';
            $end = $state[$day]['end'] ?? '17:00';
            $lines[] = ($labels[$day] ?? ucfirst($day)).': '.$start.' – '.$end;
        }

        return $lines === [] ? null : implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function dayKeyFromBlock(array $block): ?string
    {
        foreach (['day', 'dayName', 'weekday', 'label', 'name'] as $k) {
            if (! empty($block[$k]) && is_scalar($block[$k])) {
                return self::normalizeDaySlug(trim((string) $block[$k]));
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array{start: string, end: string}|null
     */
    private static function firstSlot(array $block): ?array
    {
        foreach (['slots', 'times', 'ranges', 'hours'] as $k) {
            if (empty($block[$k]) || ! is_array($block[$k])) {
                continue;
            }
            $list = $block[$k];
            $first = $list[0] ?? null;
            if (! is_array($first)) {
                continue;
            }
            $s = $first['start'] ?? $first['from'] ?? null;
            $e = $first['end'] ?? $first['to'] ?? null;
            if ($s !== null && $e !== null) {
                $start = self::normalizeTimeString((string) $s);
                $end = self::normalizeTimeString((string) $e);
                if ($start !== null && $end !== null) {
                    return ['start' => $start, 'end' => $end];
                }
            }
        }

        return null;
    }

    private static function normalizeDaySlug(string $input): ?string
    {
        $n = strtolower(trim($input));
        $n = preg_replace('/[^a-z]/', '', $n) ?? $n;
        if (strlen($n) >= 3) {
            $three = substr($n, 0, 3);

            return match ($three) {
                'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun' => $three,
                default => null,
            };
        }

        return null;
    }

    private static function normalizeTimeString(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $value, $m)) {
            $h = (int) $m[1];
            $i = (int) $m[2];
            if ($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59) {
                return sprintf('%02d:%02d', $h, $i);
            }
        }

        try {
            $c = Carbon::parse($value);

            return $c->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<mixed>  $arr
     */
    private static function isAssoc(array $arr): bool
    {
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     */
    private static function mobileGridHasSelection(array $grid): bool
    {
        foreach (self::DAY_KEYS as $day) {
            if (($grid[$day]['morning'] ?? false) || ($grid[$day]['evening'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     * @param  array<string, mixed>  $block
     */
    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     * @param  list<mixed>  $periods
     */
    private static function applyPeriodListToMobileGrid(array &$grid, string $day, array $periods): void
    {
        foreach ($periods as $period) {
            if (! is_string($period) && ! is_numeric($period)) {
                continue;
            }
            $normalized = self::normalizePeriodSlug((string) $period);
            if ($normalized === 'morning') {
                $grid[$day]['morning'] = true;
            } elseif ($normalized === 'evening') {
                $grid[$day]['evening'] = true;
            } elseif ($normalized === 'afternoon') {
                $grid[$day]['morning'] = true;
            }
        }
    }

    /**
     * @param  array<mixed>|null  $raw
     */
    private static function isMobileWeeklyDayMap(?array $raw): bool
    {
        if ($raw === null || $raw === [] || ! self::isAssoc($raw)) {
            return false;
        }

        foreach ($raw as $key => $value) {
            if (self::normalizeDaySlug((string) $key) === null || ! is_array($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     * @param  array<string, mixed>  $block
     */
    private static function applyBlockToMobileGrid(array &$grid, string $day, array $block): void
    {
        if (array_is_list($block)) {
            self::applyPeriodListToMobileGrid($grid, $day, $block);

            return;
        }

        foreach (['morning', 'am'] as $period) {
            if (array_key_exists($period, $block) && self::isTruthy($block[$period])) {
                $grid[$day]['morning'] = true;
            }
        }
        foreach (['evening', 'pm'] as $period) {
            if (array_key_exists($period, $block) && self::isTruthy($block[$period])) {
                $grid[$day]['evening'] = true;
            }
        }

        foreach (['slots', 'times', 'ranges', 'hours', 'intervals', 'periods'] as $k) {
            if (empty($block[$k]) || ! is_array($block[$k])) {
                continue;
            }
            foreach ($block[$k] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $start = self::normalizeTimeString((string) ($slot['start'] ?? $slot['from'] ?? ''));
                $end = self::normalizeTimeString((string) ($slot['end'] ?? $slot['to'] ?? ''));
                if ($start === null || $end === null) {
                    continue;
                }
                if (self::slotOverlapsMorning($start, $end)) {
                    $grid[$day]['morning'] = true;
                }
                if (self::slotOverlapsEvening($start, $end)) {
                    $grid[$day]['evening'] = true;
                }
            }
        }
    }

    /**
     * @param  array<string, array{morning: bool, evening: bool}>  $grid
     */
    private static function mergeSummaryIntoMobileGrid(array &$grid, ?string $summary): void
    {
        if ($summary === null || trim($summary) === '') {
            return;
        }

        $text = str_replace(["\r\n", "\r"], "\n", trim($summary));
        $chunks = preg_split('/\s*·\s*|\n+/', $text) ?: [];
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '') {
                continue;
            }

            if (preg_match('/^(mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\s*:\s*(.+)$/iu', $chunk, $labeled)) {
                $day = self::normalizeDaySlug($labeled[1]);
                if ($day === null) {
                    continue;
                }
                $rest = $labeled[2];
                if (preg_match('/\bmorning\b/i', $rest)) {
                    $grid[$day]['morning'] = true;
                }
                if (preg_match('/\bevening\b/i', $rest)) {
                    $grid[$day]['evening'] = true;
                }
                if (preg_match('/\bafternoon\b/i', $rest)) {
                    $grid[$day]['morning'] = true;
                }

                continue;
            }

            if (! preg_match('/\b(mon|tue|wed|thu|fri|sat|sun|monday|tuesday|wednesday|thursday|friday|saturday|sunday)\b/i', $chunk, $dayMatch)) {
                continue;
            }
            $day = self::normalizeDaySlug($dayMatch[1]);
            if ($day === null) {
                continue;
            }
            if (preg_match('/\bmorning\b/i', $chunk)) {
                $grid[$day]['morning'] = true;
            }
            if (preg_match('/\bevening\b/i', $chunk)) {
                $grid[$day]['evening'] = true;
            }
            if (preg_match('/\bafternoon\b/i', $chunk)) {
                $grid[$day]['morning'] = true;
            }
            if (preg_match('/(\d{1,2}:\d{2})\s*[–\-—to]+\s*(\d{1,2}:\d{2})/iu', $chunk, $range)) {
                $start = self::normalizeTimeString($range[1]);
                $end = self::normalizeTimeString($range[2]);
                if ($start !== null && $end !== null) {
                    if (self::slotOverlapsMorning($start, $end)) {
                        $grid[$day]['morning'] = true;
                    }
                    if (self::slotOverlapsEvening($start, $end)) {
                        $grid[$day]['evening'] = true;
                    }
                }
            }
        }
    }

    private static function slotOverlapsMorning(string $start, string $end): bool
    {
        $start = self::normalizeTimeString($start) ?? self::MORNING_START;
        $end = self::normalizeTimeString($end) ?? self::MORNING_END;

        return $start < self::MORNING_END && $end > self::MORNING_START;
    }

    private static function slotOverlapsEvening(string $start, string $end): bool
    {
        $start = self::normalizeTimeString($start) ?? self::EVENING_START;
        $end = self::normalizeTimeString($end) ?? self::EVENING_END;

        return $start < self::EVENING_END && $end > self::EVENING_START;
    }

    private static function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        if (is_string($value)) {
            $v = strtolower(trim($value));

            return in_array($v, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    private static function normalizePeriodSlug(string $input): ?string
    {
        $p = strtolower(preg_replace('/[^a-z]/', '', $input) ?? $input);

        return match ($p) {
            'morning', 'am' => 'morning',
            'evening', 'pm' => 'evening',
            'afternoon' => 'afternoon',
            default => null,
        };
    }

    /**
     * Day-by-day hours from registration JSON. Legacy morning/evening lists become their default ranges.
     *
     * @return array<string, array{available: bool, periods: list<array{start: string, end: string}>}>
     */
    public static function dayScheduleState(?array $raw): array
    {
        $state = self::emptyDaySchedule();
        if ($raw === null || $raw === []) {
            return $state;
        }

        if (self::isDayPeriodSchedule($raw)) {
            foreach ($raw as $key => $value) {
                $day = self::normalizeDaySlug((string) $key);
                if ($day === null || ! is_array($value) || array_is_list($value)) {
                    continue;
                }
                $available = self::blockIsAvailable($value);
                $periods = $available ? self::periodsFromBlock($value) : [];
                $state[$day] = [
                    'available' => $available && $periods !== [],
                    'periods' => $periods,
                ];
                if (($value['status'] ?? null) === 'available' && $periods === []) {
                    $state[$day]['available'] = true;
                }
                if (($value['status'] ?? null) === 'unavailable' || ($value['available'] ?? null) === false) {
                    $state[$day] = ['available' => false, 'periods' => []];
                }
            }

            return $state;
        }

        if (self::isMobileWeeklyDayMap($raw)) {
            $grid = self::mobileGridState($raw);
            foreach (self::DAY_KEYS as $day) {
                $periods = [];
                if ($grid[$day]['morning'] ?? false) {
                    $periods[] = ['start' => self::MORNING_START, 'end' => self::MORNING_END];
                }
                if ($grid[$day]['evening'] ?? false) {
                    $periods[] = ['start' => self::EVENING_START, 'end' => self::EVENING_END];
                }
                $state[$day] = [
                    'available' => $periods !== [],
                    'periods' => $periods,
                ];
            }

            return $state;
        }

        $calendar = self::calendarState($raw);
        foreach (self::DAY_KEYS as $day) {
            if (! ($calendar[$day]['on'] ?? false)) {
                continue;
            }
            $state[$day] = [
                'available' => true,
                'periods' => [[
                    'start' => $calendar[$day]['start'] ?? '09:00',
                    'end' => $calendar[$day]['end'] ?? '17:00',
                ]],
            ];
        }

        return $state;
    }

    /**
     * @return array<string, array{available: bool, periods: list<array{start: string, end: string}>}>
     */
    public static function dayScheduleStateForEmployee(?array $raw, ?string $summary = null): array
    {
        $state = self::dayScheduleState($raw);
        if (self::dayScheduleHasAvailability($state) || self::isDayPeriodSchedule($raw)) {
            return $state;
        }

        $calendar = self::calendarStateForEmployee(null, $summary);
        foreach (self::DAY_KEYS as $day) {
            if (! ($calendar[$day]['on'] ?? false)) {
                continue;
            }
            $state[$day] = [
                'available' => true,
                'periods' => [[
                    'start' => $calendar[$day]['start'] ?? '09:00',
                    'end' => $calendar[$day]['end'] ?? '17:00',
                ]],
            ];
        }

        return $state;
    }

    /**
     * Restore a posted admin form, including incomplete time rows.
     *
     * @param  array<string, mixed>  $posted
     * @return array<string, array{available: bool, periods: list<array{start: string, end: string}>}>
     */
    public static function dayScheduleFromPosted(array $posted): array
    {
        $state = self::emptyDaySchedule();
        foreach (self::DAY_KEYS as $day) {
            $row = $posted[$day] ?? null;
            if (! is_array($row)) {
                continue;
            }
            $available = strtolower(trim((string) ($row['status'] ?? 'unavailable'))) === 'available';
            $periods = [];
            if ($available && isset($row['periods']) && is_array($row['periods'])) {
                foreach ($row['periods'] as $period) {
                    if (! is_array($period)) {
                        continue;
                    }
                    $start = trim((string) ($period['start'] ?? ''));
                    $end = trim((string) ($period['end'] ?? ''));
                    $periods[] = [
                        'start' => self::normalizeTimeString($start) ?? '',
                        'end' => self::normalizeTimeString($end) ?? '',
                    ];
                }
            }
            if ($available && $periods === []) {
                $periods[] = ['start' => '', 'end' => ''];
            }
            $state[$day] = [
                'available' => $available,
                'periods' => $available ? $periods : [],
            ];
        }

        return $state;
    }

    /**
     * @param  array<string, array{available: bool, periods: list<array{start: string, end: string}>}>  $schedule
     */
    public static function summaryTextFromDaySchedule(array $schedule): ?string
    {
        $parts = [];
        foreach (self::DAY_KEYS as $day) {
            $label = self::MOBILE_DAY_LABELS[$day] ?? ucfirst($day);
            $dayState = $schedule[$day] ?? ['available' => false, 'periods' => []];
            if (! ($dayState['available'] ?? false)) {
                $parts[] = $label.': Not available';

                continue;
            }
            $ranges = [];
            foreach ($dayState['periods'] ?? [] as $period) {
                $start = self::normalizeTimeString((string) ($period['start'] ?? ''));
                $end = self::normalizeTimeString((string) ($period['end'] ?? ''));
                if ($start === null || $end === null || $start === $end) {
                    continue;
                }
                $text = $start.'–'.$end;
                if ($end < $start) {
                    $text .= ' (overnight)';
                }
                $ranges[] = $text;
            }
            $parts[] = $ranges === [] ? $label.': Available' : $label.': '.implode(', ', $ranges);
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public static function summaryTextFromStored(?array $raw): ?string
    {
        if ($raw === null || $raw === []) {
            return null;
        }
        if (self::isDayPeriodSchedule($raw)) {
            return self::summaryTextFromDaySchedule(self::dayScheduleState($raw));
        }

        return self::summaryTextFromMobileGrid(self::mobileGridState($raw));
    }

    public static function isOvernight(string $start, string $end): bool
    {
        $start = self::normalizeTimeString($start);
        $end = self::normalizeTimeString($end);
        if ($start === null || $end === null) {
            return false;
        }

        return $end < $start;
    }

    /**
     * @return array<string, array{available: bool, periods: list<array{start: string, end: string}>}>
     */
    private static function emptyDaySchedule(): array
    {
        $state = [];
        foreach (self::DAY_KEYS as $day) {
            $state[$day] = ['available' => false, 'periods' => []];
        }

        return $state;
    }

    /**
     * @param  array<string, mixed>  $posted
     */
    private static function postedUsesDaySchedule(array $posted): bool
    {
        foreach (self::DAY_KEYS as $day) {
            $row = $posted[$day] ?? null;
            if (is_array($row) && (array_key_exists('status', $row) || array_key_exists('periods', $row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $posted
     * @return array<string, array{status: string, periods: list<array{start: string, end: string}>}>|null
     */
    private static function encodeDaySchedule(array $posted): ?array
    {
        $map = [];
        foreach (self::DAY_KEYS as $day) {
            $row = is_array($posted[$day] ?? null) ? $posted[$day] : [];
            $available = strtolower(trim((string) ($row['status'] ?? 'unavailable'))) === 'available';
            $periods = [];
            if ($available && isset($row['periods']) && is_array($row['periods'])) {
                foreach ($row['periods'] as $period) {
                    if (! is_array($period)) {
                        continue;
                    }
                    $start = self::normalizeTimeString((string) ($period['start'] ?? ''));
                    $end = self::normalizeTimeString((string) ($period['end'] ?? ''));
                    if ($start === null || $end === null || $start === $end) {
                        continue;
                    }
                    $periods[] = ['start' => $start, 'end' => $end];
                }
            }
            $label = self::MOBILE_DAY_LABELS[$day] ?? ucfirst($day);
            $map[$label] = [
                'status' => $available ? 'available' : 'unavailable',
                'periods' => $available ? $periods : [],
            ];
        }

        return $map;
    }

    /**
     * @param  array<string, array{available: bool, periods: list<array{start: string, end: string}>}>  $schedule
     */
    private static function dayScheduleHasAvailability(array $schedule): bool
    {
        foreach (self::DAY_KEYS as $day) {
            if (($schedule[$day]['available'] ?? false) && ($schedule[$day]['periods'] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<mixed>|null  $raw
     */
    private static function isDayPeriodSchedule(?array $raw): bool
    {
        if ($raw === null || $raw === [] || ! self::isAssoc($raw)) {
            return false;
        }

        foreach ($raw as $key => $value) {
            if (self::normalizeDaySlug((string) $key) === null || ! is_array($value) || array_is_list($value)) {
                return false;
            }
            if (! array_key_exists('periods', $value) && ! array_key_exists('status', $value) && ! array_key_exists('available', $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function blockIsAvailable(array $block): bool
    {
        if (($block['status'] ?? null) === 'unavailable') {
            return false;
        }
        if (array_key_exists('available', $block) && $block['available'] === false) {
            return false;
        }
        if (($block['status'] ?? null) === 'available' || ($block['available'] ?? null) === true) {
            return true;
        }

        return self::periodsFromBlock($block) !== [];
    }

    /**
     * @param  array<string, mixed>  $block
     * @return list<array{start: string, end: string}>
     */
    private static function periodsFromBlock(array $block): array
    {
        $periods = [];
        foreach (['periods', 'slots', 'times', 'ranges', 'hours', 'intervals'] as $key) {
            if (empty($block[$key]) || ! is_array($block[$key])) {
                continue;
            }
            foreach ($block[$key] as $slot) {
                if (! is_array($slot)) {
                    continue;
                }
                $start = self::normalizeTimeString((string) ($slot['start'] ?? $slot['from'] ?? ''));
                $end = self::normalizeTimeString((string) ($slot['end'] ?? $slot['to'] ?? ''));
                if ($start === null || $end === null || $start === $end) {
                    continue;
                }
                $periods[] = ['start' => $start, 'end' => $end];
            }
        }

        return $periods;
    }
}
