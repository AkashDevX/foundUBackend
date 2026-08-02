<?php

namespace App\Support;

/**
 * Cleaning Industry Award rates from client PDF (effective 1 July 2025).
 *
 * @return array<string, array<string, array<string, float>>>
 */
final class PayrollAwardRateDefaults
{
    public static function all(): array
    {
        return [
            'full_time' => [
                'level_2' => self::fullTimeLevel2(),
                'level_1' => self::fullTimeLevel1(),
            ],
            'part_time' => [
                'level_2' => self::partTimeLevel2(),
                'level_1' => self::partTimeLevel1(),
            ],
            'casual' => [
                'level_1' => self::casualLevel1(),
                'level_2' => self::casualLevel2(),
            ],
        ];
    }

    /**
     * @return array<string, float>
     */
    private static function fullTimeLevel2(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 26.70,
            PayrollRateTypes::WEEKDAY_PENALTY => 30.71,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 34.71,
            PayrollRateTypes::SATURDAY => 40.05,
            PayrollRateTypes::SUNDAY => 53.40,
            PayrollRateTypes::PUBLIC_HOLIDAY => 66.75,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 40.05,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 53.40,
            PayrollRateTypes::OVERTIME_SUNDAY => 53.40,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 66.75,
        ];
    }

    /**
     * @return array<string, float>
     */
    private static function partTimeLevel2(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 30.71,
            PayrollRateTypes::WEEKDAY_PENALTY => 34.71,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 34.71,
            PayrollRateTypes::SATURDAY => 44.06,
            PayrollRateTypes::SUNDAY => 57.41,
            PayrollRateTypes::PUBLIC_HOLIDAY => 70.76,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 40.05,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 53.40,
            PayrollRateTypes::OVERTIME_SUNDAY => 53.40,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 66.75,
        ];
    }

    /**
     * @return array<string, float>
     */
    private static function fullTimeLevel1(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 25.85,
            PayrollRateTypes::WEEKDAY_PENALTY => 29.73,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 33.61,
            PayrollRateTypes::SATURDAY => 38.78,
            PayrollRateTypes::SUNDAY => 51.70,
            PayrollRateTypes::PUBLIC_HOLIDAY => 64.63,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 38.78,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 51.70,
            PayrollRateTypes::OVERTIME_SUNDAY => 51.70,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 64.63,
        ];
    }

    /**
     * @return array<string, float>
     */
    private static function partTimeLevel1(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 29.73,
            PayrollRateTypes::WEEKDAY_PENALTY => 33.61,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 33.61,
            PayrollRateTypes::SATURDAY => 42.65,
            PayrollRateTypes::SUNDAY => 55.58,
            PayrollRateTypes::PUBLIC_HOLIDAY => 68.50,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 38.78,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 51.70,
            PayrollRateTypes::OVERTIME_SUNDAY => 51.70,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 64.63,
        ];
    }

    /**
     * @return array<string, float>
     */
    private static function casualLevel1(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 32.31,
            PayrollRateTypes::WEEKDAY_PENALTY => 36.19,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 40.07,
            PayrollRateTypes::SATURDAY => 45.24,
            PayrollRateTypes::SUNDAY => 58.16,
            PayrollRateTypes::PUBLIC_HOLIDAY => 71.09,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 45.24,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 58.16,
            PayrollRateTypes::OVERTIME_SUNDAY => 58.16,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 71.09,
        ];
    }

    /**
     * Casual Level 2 not listed in PDF — derived using the same uplift ratio as Level 1 casual vs FT.
     *
     * @return array<string, float>
     */
    private static function casualLevel2(): array
    {
        $ft = self::fullTimeLevel2();
        $casualL1 = self::casualLevel1();
        $ftL1 = self::fullTimeLevel1();
        $ratio = $casualL1[PayrollRateTypes::WEEKDAY_ORDINARY] / $ftL1[PayrollRateTypes::WEEKDAY_ORDINARY];

        $rates = [];
        foreach ($ft as $key => $amount) {
            $rates[$key] = round($amount * $ratio, 2);
        }

        return $rates;
    }
}
