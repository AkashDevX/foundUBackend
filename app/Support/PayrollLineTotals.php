<?php

namespace App\Support;

/**
 * Classify payroll run lines into paysheet buckets.
 *
 * Accrual valuation lines are informational only and must never inflate gross pay.
 * Statutory deductions (tax, super, etc.) are not modelled yet — callers should
 * treat deductions as empty / not recorded rather than inventing net-pay math.
 */
final class PayrollLineTotals
{
    /**
     * @param  iterable<int, array{rate_type?: string, description?: string, label?: string, hours?: float|int|string, rate?: float|int|string, amount?: float|int|string, sort_order?: int}|object>  $lines
     * @return array{
     *     earnings: list<array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int, category: string}>,
     *     accruals: list<array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int, category: string}>,
     *     unpaid_leave: list<array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int, category: string}>,
     *     deductions: list<array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int, category: string}>,
     *     worked_hours: float,
     *     paid_leave_hours: float,
     *     unpaid_leave_hours: float,
     *     accrual_hours: float,
     *     ordinary_amount: float,
     *     penalty_amount: float,
     *     overtime_amount: float,
     *     allowance_amount: float,
     *     paid_leave_amount: float,
     *     gross_pay: float,
     *     deductions_total: float,
     *     net_pay: float|null,
     *     accruals_value: float,
     *     deductions_recorded: bool,
     * }
     */
    public static function summarize(iterable $lines): array
    {
        $earnings = [];
        $accruals = [];
        $unpaidLeave = [];
        $deductions = [];

        $workedHours = 0.0;
        $paidLeaveHours = 0.0;
        $unpaidLeaveHours = 0.0;
        $accrualHours = 0.0;

        $ordinaryAmount = 0.0;
        $penaltyAmount = 0.0;
        $overtimeAmount = 0.0;
        $allowanceAmount = 0.0;
        $paidLeaveAmount = 0.0;
        $grossPay = 0.0;
        $deductionsTotal = 0.0;
        $accrualsValue = 0.0;
        $deductionsRecorded = false;

        foreach ($lines as $raw) {
            $normalized = self::normalizeLine($raw);
            $category = self::categoryFor($normalized['rate_type']);

            match ($category) {
                'earning' => $earnings[] = $normalized + ['category' => $category],
                'accrual' => $accruals[] = $normalized + ['category' => $category],
                'unpaid_leave' => $unpaidLeave[] = $normalized + ['category' => $category],
                'deduction' => $deductions[] = $normalized + ['category' => $category],
                default => null,
            };

            if ($category === 'earning') {
                $grossPay += $normalized['amount'];
                $bucket = self::earningBucket($normalized['rate_type']);
                if ($bucket === 'ordinary') {
                    $ordinaryAmount += $normalized['amount'];
                    $workedHours += $normalized['hours'];
                } elseif ($bucket === 'penalty') {
                    $penaltyAmount += $normalized['amount'];
                    $workedHours += $normalized['hours'];
                } elseif ($bucket === 'overtime') {
                    $overtimeAmount += $normalized['amount'];
                    $workedHours += $normalized['hours'];
                } elseif ($bucket === 'allowance') {
                    $allowanceAmount += $normalized['amount'];
                } elseif ($bucket === 'paid_leave') {
                    $paidLeaveAmount += $normalized['amount'];
                    $paidLeaveHours += $normalized['hours'];
                }
            } elseif ($category === 'accrual') {
                $accrualHours += $normalized['hours'];
                $accrualsValue += $normalized['amount'];
            } elseif ($category === 'unpaid_leave') {
                $unpaidLeaveHours += $normalized['hours'];
            } elseif ($category === 'deduction') {
                $deductionsRecorded = true;
                $deductionsTotal += abs($normalized['amount']);
            }
        }

        $sort = static fn (array $a, array $b): int => ($a['sort_order'] <=> $b['sort_order'])
            ?: strcmp($a['label'], $b['label']);

        usort($earnings, $sort);
        usort($accruals, $sort);
        usort($unpaidLeave, $sort);
        usort($deductions, $sort);

        $grossPay = round($grossPay, 2);
        $deductionsTotal = round($deductionsTotal, 2);

        return [
            'earnings' => $earnings,
            'accruals' => $accruals,
            'unpaid_leave' => $unpaidLeave,
            'deductions' => $deductions,
            'worked_hours' => round($workedHours, 2),
            'paid_leave_hours' => round($paidLeaveHours, 2),
            'unpaid_leave_hours' => round($unpaidLeaveHours, 2),
            'accrual_hours' => round($accrualHours, 2),
            'ordinary_amount' => round($ordinaryAmount, 2),
            'penalty_amount' => round($penaltyAmount, 2),
            'overtime_amount' => round($overtimeAmount, 2),
            'allowance_amount' => round($allowanceAmount, 2),
            'paid_leave_amount' => round($paidLeaveAmount, 2),
            'gross_pay' => $grossPay,
            'deductions_total' => $deductionsTotal,
            // Net pay is only meaningful once real deduction lines exist.
            'net_pay' => $deductionsRecorded ? round($grossPay - $deductionsTotal, 2) : null,
            'accruals_value' => round($accrualsValue, 2),
            'deductions_recorded' => $deductionsRecorded,
        ];
    }

    public static function categoryFor(string $rateType): string
    {
        return match ($rateType) {
            PayrollRateTypes::SICK_LEAVE_ACCRUAL,
            PayrollRateTypes::ANNUAL_LEAVE_ACCRUAL => 'accrual',
            PayrollRateTypes::UNPAID_LEAVE_TAKEN => 'unpaid_leave',
            // Reserved for future PAYG / super / garnishes once stored on runs.
            'deduction',
            'tax',
            'payg',
            'superannuation',
            'salary_sacrifice' => 'deduction',
            default => 'earning',
        };
    }

    public static function earningBucket(string $rateType): string
    {
        return match ($rateType) {
            PayrollRateTypes::ALLOWANCE => 'allowance',
            PayrollRateTypes::SICK_LEAVE_TAKEN,
            PayrollRateTypes::ANNUAL_LEAVE_TAKEN,
            PayrollRateTypes::LEAVE_TAKEN => 'paid_leave',
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H,
            PayrollRateTypes::OVERTIME_SUNDAY,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 'overtime',
            PayrollRateTypes::WEEKDAY_PENALTY,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT,
            PayrollRateTypes::SATURDAY,
            PayrollRateTypes::SUNDAY,
            PayrollRateTypes::PUBLIC_HOLIDAY => 'penalty',
            default => 'ordinary',
        };
    }

    /**
     * @param  array<string, mixed>|object  $line
     * @return array{rate_type: string, label: string, hours: float, rate: float, amount: float, sort_order: int}
     */
    private static function normalizeLine(array|object $line): array
    {
        $get = static function (string $key, mixed $default = null) use ($line): mixed {
            if (is_array($line)) {
                return $line[$key] ?? $default;
            }

            return $line->{$key} ?? $default;
        };

        $rateType = (string) $get('rate_type', '');
        $label = trim((string) ($get('label') ?? $get('description') ?? ''));
        if ($label === '') {
            $label = PayrollRateTypes::label($rateType);
        }

        return [
            'rate_type' => $rateType,
            'label' => $label,
            'hours' => round((float) $get('hours', 0), 2),
            'rate' => round((float) $get('rate', 0), 2),
            'amount' => round((float) $get('amount', 0), 2),
            'sort_order' => (int) $get('sort_order', 0),
        ];
    }
}
