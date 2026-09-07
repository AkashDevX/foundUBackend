<?php

namespace App\Support;

use App\Models\Employee;

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

    /**
     * Prefer primary job title wage when it is in force on $asOf; otherwise award ordinary rate.
     *
     * @param  array<string, float>  $rates
     */
    public static function ordinaryHourlyRateForEmployee(Employee $employee, array $rates, ?\Carbon\CarbonInterface $asOf = null): float
    {
        $title = self::primaryTitle($employee);
        $asOf = $asOf ?? DisplayTimezone::now();
        if ($title !== null && $title->wageAppliesOn($asOf)) {
            return (float) $title->hourly_wage;
        }

        return self::ordinaryHourlyRate($rates);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\EmployeeScheduleShift>|null  $scheduleShifts
     */
    public static function employeeUsesTitleWages(Employee $employee, ?\Illuminate\Support\Collection $scheduleShifts = null): bool
    {
        $primary = self::primaryTitle($employee);
        if ($primary !== null && $primary->hasHourlyWage()) {
            return true;
        }

        if ($employee->relationLoaded('jobTitles') || $employee->exists) {
            $employee->loadMissing('jobTitles');
            if ($employee->jobTitles->contains(static fn ($jt) => $jt->hasHourlyWage())) {
                return true;
            }
        }

        foreach ($scheduleShifts ?? [] as $shift) {
            $shift->loadMissing('jobTitle');
            if ($shift->jobTitle !== null && $shift->jobTitle->hasHourlyWage()) {
                return true;
            }
        }

        return false;
    }

    private static function primaryTitle(Employee $employee): ?\App\Models\JobTitle
    {
        if (! $employee->relationLoaded('assignedJobTitle') && ! $employee->exists) {
            return null;
        }

        $employee->loadMissing('assignedJobTitle');

        return $employee->assignedJobTitle;
    }
}
