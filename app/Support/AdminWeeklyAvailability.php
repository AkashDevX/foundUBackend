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
                $slot = self::firstSlot($block);
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
                    $state[$day]['start'] = self::normalizeTimeString($m[1]);
                    $state[$day]['end'] = self::normalizeTimeString($m[2]);
                } elseif (is_array($value)) {
                    $slot = self::firstSlot(['slots' => is_array($value) && array_is_list($value) ? $value : [$value]]);
                    if ($slot !== null) {
                        $state[$day]['start'] = $slot['start'];
                        $state[$day]['end'] = $slot['end'];
                    }
                }
            }
        }

        return $state;
    }

    public static function encodeFromRequest(Request $request): ?array
    {
        $blocks = [];
        foreach (self::DAY_KEYS as $day) {
            if (! $request->boolean("availability.$day.on")) {
                continue;
            }
            $start = (string) $request->input("availability.$day.start", '09:00');
            $end = (string) $request->input("availability.$day.end", '17:00');
            $start = self::normalizeTimeString($start) ?? '09:00';
            $end = self::normalizeTimeString($end) ?? '17:00';
            $blocks[] = [
                'day' => $day,
                'slots' => [['start' => $start, 'end' => $end]],
            ];
        }

        return $blocks === [] ? null : $blocks;
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
}
