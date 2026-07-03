<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class PayrollCalculator
{
    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @param  Collection<int, \App\Models\PublicHoliday>  $publicHolidays
     * @param  array<string, float>  $rates
     * @return array{
     *     lines: list<array{rate_type: string, label: string, hours: float, rate: float, amount: float}>,
     *     total_hours: float,
     *     total_amount: float,
     *     sick_leave_accrued: float,
     *     annual_leave_accrued: float,
     *     sick_leave_accrued_amount: float,
     *     annual_leave_accrued_amount: float,
     * }
     */
    public static function calculateForEmployee(
        Employee $employee,
        Collection $entries,
        Collection $publicHolidays,
        array $rates,
        CarbonInterface $fortnightStart,
        CarbonInterface $fortnightEnd,
    ): array {
        $tz = DisplayTimezone::name();
        $isNonRotating = (bool) ($employee->is_non_rotating_shift ?? false);
        $holidayDates = $publicHolidays
            ->map(static fn ($h) => $h->holiday_date?->toDateString())
            ->filter()
            ->flip();

        $sessions = self::extractSessions($entries, $fortnightStart, $fortnightEnd, $tz);

        /** @var list<array{at: CarbonInterface, base_type: string, week_key: string}> $chunks */
        $chunks = [];
        foreach ($sessions as $session) {
            $chunks = array_merge($chunks, self::sessionToChunks($session, $tz, $holidayDates, $isNonRotating));
        }

        usort($chunks, static fn (array $a, array $b): int => $a['at']->getTimestamp() <=> $b['at']->getTimestamp());

        $employmentType = (string) ($employee->employment_type ?? '');
        $weeklyThreshold = self::weeklyHoursThreshold($employee, $employmentType);
        $applyOvertime = in_array($employmentType, ['full_time', 'part_time'], true);

        /** @var array<string, list<array{at: CarbonInterface, base_type: string, week_key: string, is_overtime: bool, final_type: string}>> $byWeek */
        $byWeek = [];
        foreach ($chunks as $chunk) {
            $byWeek[$chunk['week_key']][] = array_merge($chunk, [
                'is_overtime' => false,
                'final_type' => $chunk['base_type'],
            ]);
        }

        if ($applyOvertime) {
            foreach ($byWeek as $weekKey => $weekChunks) {
                $ordinaryMinutes = count($weekChunks);
                $thresholdMinutes = (int) round($weeklyThreshold * 60);
                if ($ordinaryMinutes <= $thresholdMinutes) {
                    continue;
                }

                $otCount = $ordinaryMinutes - $thresholdMinutes;
                $startOtIndex = $ordinaryMinutes - $otCount;

                $monSatOtUsed = 0;
                for ($i = $startOtIndex; $i < $ordinaryMinutes; $i++) {
                    $weekChunks[$i]['is_overtime'] = true;
                    $base = $weekChunks[$i]['base_type'];
                    $at = $weekChunks[$i]['at'];
                    $dayOfWeek = $at->dayOfWeek;

                    if ($base === PayrollRateTypes::PUBLIC_HOLIDAY) {
                        $weekChunks[$i]['final_type'] = PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY;
                    } elseif ($dayOfWeek === Carbon::SUNDAY) {
                        $weekChunks[$i]['final_type'] = PayrollRateTypes::OVERTIME_SUNDAY;
                    } elseif ($monSatOtUsed < 120) {
                        $weekChunks[$i]['final_type'] = PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H;
                        $monSatOtUsed++;
                    } else {
                        $weekChunks[$i]['final_type'] = PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H;
                    }
                }

                $byWeek[$weekKey] = $weekChunks;
            }
        }

        /** @var array<string, float> $hoursByType */
        $hoursByType = [];
        $totalMinutes = 0;
        foreach ($byWeek as $weekChunks) {
            foreach ($weekChunks as $chunk) {
                $type = $chunk['final_type'];
                $hoursByType[$type] = ($hoursByType[$type] ?? 0) + (1 / 60);
                $totalMinutes++;
            }
        }

        $lines = [];
        $sort = 0;
        foreach (PayrollRateTypes::awardRateKeys() as $rateType) {
            $hours = round($hoursByType[$rateType] ?? 0, 2);
            if ($hours <= 0) {
                continue;
            }
            $rate = (float) ($rates[$rateType] ?? 0);
            $amount = round($hours * $rate, 2);
            $lines[] = [
                'rate_type' => $rateType,
                'label' => PayrollRateTypes::label($rateType),
                'hours' => $hours,
                'rate' => $rate,
                'amount' => $amount,
                'sort_order' => $sort++,
            ];
        }

        foreach (self::parseAllowances($employee) as $allowance) {
            $amount = round((float) $allowance['amount'], 2);
            if ($amount <= 0) {
                continue;
            }
            $lines[] = [
                'rate_type' => PayrollRateTypes::ALLOWANCE,
                'label' => (string) $allowance['name'],
                'hours' => 0,
                'rate' => $amount,
                'amount' => $amount,
                'sort_order' => $sort++,
            ];
        }

        $totalHours = round($totalMinutes / 60, 2);
        $totalAmount = round(array_sum(array_column($lines, 'amount')), 2);

        $ordinaryRate = PayrollEmployeeRates::ordinaryHourlyRate($rates);
        $sickAccrued = self::accrueLeaveHours($totalHours, (float) config('payroll.sick_leave_hours_per_worked', 35));
        $annualAccrued = self::accrueLeaveHours($totalHours, (float) config('payroll.annual_leave_hours_per_worked', 35));
        $sickAccruedAmount = round($sickAccrued * $ordinaryRate, 2);
        $annualAccruedAmount = round($annualAccrued * $ordinaryRate, 2);

        if ($sickAccrued > 0) {
            $lines[] = [
                'rate_type' => PayrollRateTypes::SICK_LEAVE_ACCRUAL,
                'label' => PayrollRateTypes::label(PayrollRateTypes::SICK_LEAVE_ACCRUAL),
                'hours' => $sickAccrued,
                'rate' => $ordinaryRate,
                'amount' => $sickAccruedAmount,
                'sort_order' => $sort++,
            ];
        }

        if ($annualAccrued > 0) {
            $loadingPct = (float) config('payroll.annual_leave_loading_percent', 17.5);
            $lines[] = [
                'rate_type' => PayrollRateTypes::ANNUAL_LEAVE_ACCRUAL,
                'label' => PayrollRateTypes::label(PayrollRateTypes::ANNUAL_LEAVE_ACCRUAL).' (+ '.$loadingPct.'% loading when taken)',
                'hours' => $annualAccrued,
                'rate' => $ordinaryRate,
                'amount' => $annualAccruedAmount,
                'sort_order' => $sort++,
            ];
        }

        return [
            'lines' => $lines,
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
            'sick_leave_accrued' => $sickAccrued,
            'annual_leave_accrued' => $annualAccrued,
            'sick_leave_accrued_amount' => $sickAccruedAmount,
            'annual_leave_accrued_amount' => $annualAccruedAmount,
        ];
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @return list<array{clock_in: CarbonInterface, clock_out: CarbonInterface}>
     */
    public static function extractSessions(
        Collection $entries,
        CarbonInterface $fortnightStart,
        CarbonInterface $fortnightEnd,
        string $tz,
    ): array {
        $rangeStart = $fortnightStart->copy()->timezone($tz)->startOfDay();
        $rangeEnd = $fortnightEnd->copy()->timezone($tz)->endOfDay();

        $sorted = $entries
            ->sortBy(static fn (TimeClockEntry $e) => [$e->clocked_at?->getTimestamp() ?? 0, $e->id])
            ->values();

        $sessions = [];
        $openIn = null;

        foreach ($sorted as $entry) {
            if ($entry->event_type === TimeClockEntry::EVENT_CLOCK_IN) {
                $openIn = $entry;

                continue;
            }

            if ($entry->event_type !== TimeClockEntry::EVENT_CLOCK_OUT || ! $openIn instanceof TimeClockEntry) {
                continue;
            }

            if ($openIn->clocked_at === null || $entry->clocked_at === null) {
                $openIn = null;

                continue;
            }

            $clockIn = $openIn->clocked_at->copy()->timezone($tz);
            $clockOut = $entry->clocked_at->copy()->timezone($tz);

            if ($clockOut->lt($rangeStart) || $clockIn->gt($rangeEnd)) {
                $openIn = null;

                continue;
            }

            $sessionStart = $clockIn->lt($rangeStart) ? $rangeStart->copy() : $clockIn->copy();
            $sessionEnd = $clockOut->gt($rangeEnd) ? $rangeEnd->copy() : $clockOut->copy();

            if ($sessionEnd->gt($sessionStart)) {
                $sessions[] = [
                    'clock_in' => $sessionStart,
                    'clock_out' => $sessionEnd,
                    'original_in' => $clockIn,
                    'original_out' => $clockOut,
                ];
            }

            $openIn = null;
        }

        return $sessions;
    }

    /**
     * @param  array{clock_in: CarbonInterface, clock_out: CarbonInterface, original_in: CarbonInterface, original_out: CarbonInterface}  $session
     * @param  \Illuminate\Support\Collection<string, int>  $holidayDates
     * @return list<array{at: CarbonInterface, base_type: string, week_key: string}>
     */
    private static function sessionToChunks(array $session, string $tz, $holidayDates, bool $isNonRotatingShift): array
    {
        $chunks = [];
        $cursor = $session['clock_in']->copy();
        $end = $session['clock_out']->copy();

        $midnightShiftEnd = $isNonRotatingShift && self::qualifiesMidnightShift($session['original_out']);

        while ($cursor->lt($end)) {
            $minuteEnd = $cursor->copy()->addMinute();
            if ($minuteEnd->gt($end)) {
                $minuteEnd = $end->copy();
            }

            $dateStr = $cursor->toDateString();
            $weekKey = $cursor->copy()->startOfWeek(Carbon::MONDAY)->toDateString();
            $baseType = self::classifyMinute($cursor, $dateStr, $holidayDates, $midnightShiftEnd);

            $chunks[] = [
                'at' => $cursor->copy(),
                'base_type' => $baseType,
                'week_key' => $weekKey,
            ];

            $cursor = $minuteEnd;
        }

        return $chunks;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, int>  $holidayDates
     */
    private static function classifyMinute(
        CarbonInterface $at,
        string $dateStr,
        $holidayDates,
        bool $midnightShiftEnd,
    ): string {
        if ($holidayDates->has($dateStr)) {
            return PayrollRateTypes::PUBLIC_HOLIDAY;
        }

        $dow = $at->dayOfWeek;
        if ($dow === Carbon::SUNDAY) {
            return PayrollRateTypes::SUNDAY;
        }
        if ($dow === Carbon::SATURDAY) {
            return PayrollRateTypes::SATURDAY;
        }

        $hour = (int) $at->format('G');
        $minuteOfDay = $hour * 60 + (int) $at->format('i');

        if ($midnightShiftEnd && $minuteOfDay <= 8 * 60) {
            return PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT;
        }

        $ordinaryStart = (int) config('payroll.weekday_ordinary_start_hour', 6) * 60;
        $ordinaryEnd = (int) config('payroll.weekday_ordinary_end_hour', 18) * 60;

        if ($minuteOfDay < $ordinaryStart || $minuteOfDay >= $ordinaryEnd) {
            return PayrollRateTypes::WEEKDAY_PENALTY;
        }

        return PayrollRateTypes::WEEKDAY_ORDINARY;
    }

    private static function qualifiesMidnightShift(CarbonInterface $clockOut): bool
    {
        $dow = $clockOut->dayOfWeek;
        if ($dow === Carbon::SATURDAY || $dow === Carbon::SUNDAY) {
            return false;
        }

        $hour = (int) $clockOut->format('G');
        $minute = (int) $clockOut->format('i');

        if ($hour === 0) {
            return true;
        }

        if ($hour > 0 && $hour < 8) {
            return true;
        }

        if ($hour === 8 && $minute === 0) {
            return true;
        }

        return false;
    }

    private static function weeklyHoursThreshold(Employee $employee, string $employmentType): float
    {
        if ($employmentType === 'part_time') {
            $contracted = self::parseContractedHours($employee->hours_per_week);

            return $contracted > 0 ? $contracted : (float) config('payroll.full_time_weekly_hours', 38);
        }

        return (float) config('payroll.full_time_weekly_hours', 38);
    }

    private static function parseContractedHours(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0;
        }
        if (is_numeric($value)) {
            return max(0, (float) $value);
        }

        if (preg_match('/[\d.]+/', (string) $value, $m)) {
            return max(0, (float) $m[0]);
        }

        return 0;
    }

    /**
     * @return list<array{name: string, amount: float}>
     */
    public static function parseAllowances(Employee $employee): array
    {
        $raw = $employee->payroll_allowances_json;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? ''));
            $amount = (float) ($row['amount'] ?? 0);
            if ($name === '' || $amount <= 0) {
                continue;
            }
            $out[] = ['name' => $name, 'amount' => $amount];
        }

        return $out;
    }

    private static function accrueLeaveHours(float $workedHours, float $hoursPerAccruedHour): float
    {
        if ($hoursPerAccruedHour <= 0 || $workedHours <= 0) {
            return 0;
        }

        return round($workedHours / $hoursPerAccruedHour, 2);
    }
}
