<?php

namespace App\Support;

/**
 * Structured shift-template breaks (paid / unpaid) with a derived display summary
 * for mobile clients that still read `breaks_summary` as plain text.
 */
final class ShiftBreaks
{
    public const MAX_ITEMS = 8;

    /**
     * @param  mixed  $input  Request `shift_breaks` array or existing JSON.
     * @return list<array{label: string, minutes: int, paid: bool}>
     */
    public static function normalize(mixed $input): array
    {
        if (! is_array($input)) {
            return [];
        }

        $items = [];
        foreach ($input as $row) {
            if (! is_array($row)) {
                continue;
            }

            $label = trim((string) ($row['label'] ?? ''));
            $minutesRaw = $row['minutes'] ?? null;
            $minutes = is_numeric($minutesRaw) ? (int) $minutesRaw : 0;
            $paidRaw = $row['paid'] ?? false;
            $paid = filter_var($paidRaw, FILTER_VALIDATE_BOOLEAN)
                || $paidRaw === 1
                || $paidRaw === '1'
                || $paidRaw === 'paid';

            if ($label === '' && $minutes <= 0) {
                continue;
            }

            if ($minutes < 1 || $minutes > 480) {
                continue;
            }

            if ($label === '') {
                $label = $paid ? 'Paid break' : 'Unpaid break';
            }

            $items[] = [
                'label' => mb_substr($label, 0, 80),
                'minutes' => $minutes,
                'paid' => $paid,
            ];

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }

    /**
     * @param  list<array{label: string, minutes: int, paid: bool}>  $breaks
     */
    public static function summaryFrom(array $breaks): ?string
    {
        if ($breaks === []) {
            return null;
        }

        $parts = array_map(
            static function (array $break): string {
                $type = $break['paid'] ? 'paid' : 'unpaid';

                return sprintf('%dm %s %s', $break['minutes'], $type, $break['label']);
            },
            $breaks
        );

        $summary = implode(', ', $parts);

        return mb_substr($summary, 0, 255);
    }

    /**
     * @param  list<array{label: string, minutes: int, paid: bool}>|null  $breaks
     */
    public static function unpaidMinutesTotal(?array $breaks): int
    {
        if (! is_array($breaks) || $breaks === []) {
            return 0;
        }

        $total = 0;
        foreach ($breaks as $break) {
            if (! is_array($break) || ! empty($break['paid'])) {
                continue;
            }
            $total += max(0, (int) ($break['minutes'] ?? 0));
        }

        return $total;
    }

    /**
     * @param  list<array{label: string, minutes: int, paid: bool}>|null  $breaks
     */
    public static function paidMinutesTotal(?array $breaks): int
    {
        if (! is_array($breaks) || $breaks === []) {
            return 0;
        }

        $total = 0;
        foreach ($breaks as $break) {
            if (! is_array($break) || empty($break['paid'])) {
                continue;
            }
            $total += max(0, (int) ($break['minutes'] ?? 0));
        }

        return $total;
    }

    /**
     * Payable shift time keeps allocated paid break minutes; unpaid allocation and any
     * break time beyond the total allocated allowance are deducted.
     *
     * @param  list<array{label: string, minutes: int, paid: bool}>|null  $allocatedBreaks
     * @return array{
     *     actual_break_seconds: int,
     *     paid_allowance_seconds: int,
     *     unpaid_allowance_seconds: int,
     *     total_allowance_seconds: int,
     *     paid_kept_seconds: int,
     *     unpaid_taken_seconds: int,
     *     excess_seconds: int,
     *     deducted_seconds: int,
     * }
     */
    public static function breakPayAdjustment(int $actualBreakSeconds, ?array $allocatedBreaks): array
    {
        $actual = max(0, $actualBreakSeconds);
        $paidAllowance = self::paidMinutesTotal($allocatedBreaks) * 60;
        $unpaidAllowance = self::unpaidMinutesTotal($allocatedBreaks) * 60;
        $totalAllowance = $paidAllowance + $unpaidAllowance;

        $paidKept = min($actual, $paidAllowance);
        $excess = max(0, $actual - $totalAllowance);
        $unpaidTaken = max(0, $actual - $paidKept - $excess);
        $deducted = $unpaidTaken + $excess;

        return [
            'actual_break_seconds' => $actual,
            'paid_allowance_seconds' => $paidAllowance,
            'unpaid_allowance_seconds' => $unpaidAllowance,
            'total_allowance_seconds' => $totalAllowance,
            'paid_kept_seconds' => $paidKept,
            'unpaid_taken_seconds' => $unpaidTaken,
            'excess_seconds' => $excess,
            'deducted_seconds' => $deducted,
        ];
    }
}
