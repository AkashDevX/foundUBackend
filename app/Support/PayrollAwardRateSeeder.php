<?php

namespace App\Support;

use App\Models\PayrollAwardRate;
use Illuminate\Support\Facades\DB;

final class PayrollAwardRateSeeder
{
    public static function ensureDefaults(string $connection): void
    {
        $effectiveFrom = (string) config('payroll.default_rates_effective_from', '2025-07-01');

        $exists = PayrollAwardRate::on($connection)
            ->where('effective_from', $effectiveFrom)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection($connection)->transaction(function () use ($connection, $effectiveFrom): void {
            foreach (PayrollAwardRateDefaults::all() as $employmentType => $levels) {
                foreach ($levels as $awardLevel => $rates) {
                    foreach ($rates as $rateType => $amount) {
                        PayrollAwardRate::on($connection)->create([
                            'employment_type' => $employmentType,
                            'award_level' => $awardLevel,
                            'rate_type' => $rateType,
                            'amount' => $amount,
                            'effective_from' => $effectiveFrom,
                        ]);
                    }
                }
            }
        });
    }

    /**
     * @return array<string, float>
     */
    public static function ratesForEmployee(string $connection, ?string $employmentType, ?string $awardLevel): array
    {
        if ($employmentType === null || $awardLevel === null) {
            return [];
        }

        $effectiveFrom = (string) config('payroll.default_rates_effective_from', '2025-07-01');

        $rows = PayrollAwardRate::on($connection)
            ->where('employment_type', $employmentType)
            ->where('award_level', $awardLevel)
            ->where('effective_from', '<=', $effectiveFrom)
            ->orderByDesc('effective_from')
            ->get();

        if ($rows->isEmpty()) {
            self::ensureDefaults($connection);
            $rows = PayrollAwardRate::on($connection)
                ->where('employment_type', $employmentType)
                ->where('award_level', $awardLevel)
                ->where('effective_from', $effectiveFrom)
                ->get();
        }

        $latestEffective = $rows->max('effective_from');
        $filtered = $rows->where('effective_from', $latestEffective);

        $map = [];
        foreach ($filtered as $row) {
            $map[(string) $row->rate_type] = (float) $row->amount;
        }

        return $map;
    }
}
