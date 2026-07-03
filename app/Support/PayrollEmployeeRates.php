<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;

final class PayrollEmployeeRates
{
    /**
     * Award table rates merged with per-employee overrides from payroll_rates_json.
     *
     * @return array<string, float>
     */
    public static function forEmployee(string $connection, Employee $employee): array
    {
        $base = PayrollAwardRateSeeder::ratesForEmployee(
            $connection,
            $employee->employment_type,
            $employee->award_level
        );

        $overrides = is_array($employee->payroll_rates_json) ? $employee->payroll_rates_json : [];
        foreach ($overrides as $rateType => $amount) {
            if (! is_string($rateType) || ! in_array($rateType, PayrollRateTypes::awardRateKeys(), true)) {
                continue;
            }
            if (is_numeric($amount)) {
                $base[$rateType] = round((float) $amount, 2);
            }
        }

        return $base;
    }

    /**
     * @return array<string, float>
     */
    public static function fromRequest(array $submitted, string $connection, Employee $employee): array
    {
        $merged = self::forEmployee($connection, $employee);

        foreach (PayrollRateTypes::awardRateKeys() as $rateType) {
            if (! isset($submitted[$rateType]) || ! is_numeric($submitted[$rateType])) {
                continue;
            }
            $merged[$rateType] = round((float) $submitted[$rateType], 2);
        }

        return $merged;
    }

    /**
     * @param  array<string, float>  $rates
     * @return array<string, float>
     */
    public static function toStoredOverrides(array $rates, string $connection, Employee $employee): array
    {
        $award = PayrollAwardRateSeeder::ratesForEmployee(
            $connection,
            $employee->employment_type,
            $employee->award_level
        );

        $overrides = [];
        foreach ($rates as $rateType => $amount) {
            $awardAmount = $award[$rateType] ?? null;
            if ($awardAmount === null || round((float) $awardAmount, 2) !== round((float) $amount, 2)) {
                $overrides[$rateType] = round((float) $amount, 2);
            }
        }

        return $overrides;
    }

    public static function ordinaryHourlyRate(array $rates): float
    {
        return (float) ($rates[PayrollRateTypes::WEEKDAY_ORDINARY] ?? 0);
    }
}
