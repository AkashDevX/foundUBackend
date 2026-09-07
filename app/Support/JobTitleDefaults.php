<?php

namespace App\Support;

use App\Models\JobTitle;
use Illuminate\Support\Facades\DB;

/**
 * Default job titles and wages from PAYROLL INTEGRATION.pdf (effective 1 July 2025).
 *
 * One title per listed rate, for the bands in the document only:
 * Full-time / Part-time Level 1–2, Casual Level 1.
 */
final class JobTitleDefaults
{
    /**
     * Employment × award level combinations listed in the payroll document.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function seedBands(): array
    {
        return [
            ['full_time', 'level_1'],
            ['full_time', 'level_2'],
            ['part_time', 'level_1'],
            ['part_time', 'level_2'],
            ['casual', 'level_1'],
        ];
    }

    /**
     * Rate kinds listed as separate wages in the payroll document.
     *
     * @return list<string>
     */
    public static function seedRateKinds(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY,
            PayrollRateTypes::WEEKDAY_PENALTY,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT,
            PayrollRateTypes::SATURDAY,
            PayrollRateTypes::SUNDAY,
            PayrollRateTypes::PUBLIC_HOLIDAY,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H,
            PayrollRateTypes::OVERTIME_SUNDAY,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY,
        ];
    }

    /**
     * Short band prefix used in title names (CAS1, FT2, PT1, …).
     */
    public static function bandPrefix(string $employmentType, string $awardLevel): string
    {
        $emp = match ($employmentType) {
            'full_time' => 'FT',
            'part_time' => 'PT',
            'casual' => 'CAS',
            default => strtoupper(substr($employmentType, 0, 3)),
        };

        $level = match ($awardLevel) {
            'level_1' => '1',
            'level_2' => '2',
            default => preg_replace('/\D+/', '', $awardLevel) ?: '1',
        };

        return $emp.$level;
    }

    /**
     * Display suffix for a rate kind; empty for ordinary (base title is just the prefix).
     */
    public static function rateKindSuffix(string $rateKind): string
    {
        return match ($rateKind) {
            PayrollRateTypes::WEEKDAY_ORDINARY => '',
            PayrollRateTypes::WEEKDAY_PENALTY => 'Penalty Rates',
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 'Midnight Shift',
            PayrollRateTypes::SATURDAY => 'Saturday',
            PayrollRateTypes::SUNDAY => 'Sunday',
            PayrollRateTypes::PUBLIC_HOLIDAY => 'Public Holiday',
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 'OT Mon–Sat First 2h',
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 'OT Mon–Sat After 2h',
            PayrollRateTypes::OVERTIME_SUNDAY => 'OT Sunday',
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 'OT Public Holiday',
            default => ucfirst(str_replace('_', ' ', $rateKind)),
        };
    }

    public static function defaultName(string $employmentType, string $awardLevel, string $rateKind = PayrollRateTypes::WEEKDAY_ORDINARY): string
    {
        $prefix = self::bandPrefix($employmentType, $awardLevel);
        $suffix = self::rateKindSuffix($rateKind);

        return $suffix === '' ? $prefix : $prefix.' ('.$suffix.')';
    }

    public static function defaultWage(string $employmentType, string $awardLevel, string $rateKind): ?float
    {
        $amount = PayrollAwardRateDefaults::all()[$employmentType][$awardLevel][$rateKind] ?? null;

        return is_numeric($amount) ? round((float) $amount, 2) : null;
    }

    /**
     * Legacy name used before rate-condition titles (one per band).
     */
    public static function legacyBandName(string $employmentType, string $awardLevel): string
    {
        $level = match ($awardLevel) {
            'level_1' => 'Level 1',
            'level_2' => 'Level 2',
            default => str_replace('_', ' ', $awardLevel),
        };

        return PayrollRateTypes::employmentTypeLabel($employmentType).' Cleaner '.$level;
    }

    public static function ensureDefaults(string $connection): void
    {
        PayrollAwardRateSeeder::ensureDefaults($connection);

        $palette = JobTitle::colorPalette();
        $colorIndex = 0;
        $seedKinds = self::seedRateKinds();
        $seedBandKeys = array_map(
            static fn (array $band): string => $band[0].'|'.$band[1],
            self::seedBands()
        );

        DB::connection($connection)->transaction(function () use ($connection, $palette, &$colorIndex, $seedKinds, $seedBandKeys): void {
            $seeded = JobTitle::on($connection)
                ->whereNotNull('rate_kind')
                ->where('rate_kind', '!=', '')
                ->get();

            foreach ($seeded as $title) {
                $bandKey = (string) $title->employment_type.'|'.(string) $title->award_level;
                $keep = in_array((string) $title->rate_kind, $seedKinds, true)
                    && in_array($bandKey, $seedBandKeys, true);

                if (! $keep && $title->is_active) {
                    $title->forceFill(['is_active' => false])->save();
                }
            }

            foreach (self::seedBands() as [$employmentType, $awardLevel]) {
                foreach ($seedKinds as $rateKind) {
                    $wage = self::defaultWage($employmentType, $awardLevel, $rateKind);
                    $name = self::defaultName($employmentType, $awardLevel, $rateKind);

                    $existing = JobTitle::on($connection)
                        ->where('employment_type', $employmentType)
                        ->where('award_level', $awardLevel)
                        ->where('rate_kind', $rateKind)
                        ->first();

                    if ($existing !== null) {
                        $existing->forceFill([
                            'name' => $name,
                            'hourly_wage' => $wage,
                            'is_active' => true,
                        ])->save();

                        continue;
                    }

                    if ($rateKind === PayrollRateTypes::WEEKDAY_ORDINARY) {
                        $legacyName = self::legacyBandName($employmentType, $awardLevel);
                        $legacy = JobTitle::on($connection)
                            ->where('name', $legacyName)
                            ->where(function ($q) use ($employmentType, $awardLevel) {
                                $q->where(function ($inner) use ($employmentType, $awardLevel) {
                                    $inner->where('employment_type', $employmentType)
                                        ->where('award_level', $awardLevel);
                                })->orWhere(function ($inner) {
                                    $inner->whereNull('rate_kind');
                                });
                            })
                            ->first();

                        if ($legacy !== null && ($legacy->rate_kind === null || $legacy->rate_kind === '')) {
                            $legacy->forceFill([
                                'name' => $name,
                                'employment_type' => $employmentType,
                                'award_level' => $awardLevel,
                                'rate_kind' => $rateKind,
                                'hourly_wage' => $wage,
                                'is_active' => true,
                            ])->save();

                            continue;
                        }
                    }

                    $byName = JobTitle::on($connection)->where('name', $name)->first();
                    if ($byName !== null) {
                        $byName->forceFill([
                            'employment_type' => $employmentType,
                            'award_level' => $awardLevel,
                            'rate_kind' => $rateKind,
                            'hourly_wage' => $wage,
                            'is_active' => true,
                        ])->save();

                        continue;
                    }

                    JobTitle::on($connection)->create([
                        'name' => $name,
                        'employment_type' => $employmentType,
                        'award_level' => $awardLevel,
                        'rate_kind' => $rateKind,
                        'hourly_wage' => $wage,
                        'color' => $palette[$colorIndex % count($palette)],
                        'is_active' => true,
                    ]);
                    $colorIndex++;
                }
            }
        });
    }
}
