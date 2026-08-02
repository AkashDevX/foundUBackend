<?php

namespace App\Support;

final class PayrollRateTypes
{
    public const WEEKDAY_ORDINARY = 'weekday_ordinary';

    public const WEEKDAY_PENALTY = 'weekday_penalty';

    public const WEEKDAY_MIDNIGHT_SHIFT = 'weekday_midnight_shift';

    public const SATURDAY = 'saturday';

    public const SUNDAY = 'sunday';

    public const PUBLIC_HOLIDAY = 'public_holiday';

    public const OVERTIME_MON_SAT_FIRST_2H = 'overtime_mon_sat_first_2h';

    public const OVERTIME_MON_SAT_AFTER_2H = 'overtime_mon_sat_after_2h';

    public const OVERTIME_SUNDAY = 'overtime_sunday';

    public const OVERTIME_PUBLIC_HOLIDAY = 'overtime_public_holiday';

    public const ALLOWANCE = 'allowance';

    public const SICK_LEAVE_ACCRUAL = 'sick_leave_accrual';

    public const ANNUAL_LEAVE_ACCRUAL = 'annual_leave_accrual';

    public const SICK_LEAVE_TAKEN = 'sick_leave_taken';

    public const ANNUAL_LEAVE_TAKEN = 'annual_leave_taken';

    public const LEAVE_TAKEN = 'leave_taken';

    public const UNPAID_LEAVE_TAKEN = 'unpaid_leave_taken';

    /**
     * @return list<string>
     */
    public static function awardRateKeys(): array
    {
        return [
            self::WEEKDAY_ORDINARY,
            self::WEEKDAY_PENALTY,
            self::WEEKDAY_MIDNIGHT_SHIFT,
            self::SATURDAY,
            self::SUNDAY,
            self::PUBLIC_HOLIDAY,
            self::OVERTIME_MON_SAT_FIRST_2H,
            self::OVERTIME_MON_SAT_AFTER_2H,
            self::OVERTIME_SUNDAY,
            self::OVERTIME_PUBLIC_HOLIDAY,
        ];
    }

    public static function label(string $rateType): string
    {
        return match ($rateType) {
            self::WEEKDAY_ORDINARY => 'Mon–Fri ordinary (6am–6pm)',
            self::WEEKDAY_PENALTY => 'Mon–Fri penalty (before 6am / after 6pm)',
            self::WEEKDAY_MIDNIGHT_SHIFT => 'Mon–Fri midnight shift (finish after midnight, ≤8am)',
            self::SATURDAY => 'Saturday',
            self::SUNDAY => 'Sunday',
            self::PUBLIC_HOLIDAY => 'Public holiday',
            self::OVERTIME_MON_SAT_FIRST_2H => 'Overtime Mon–Sat (first 2 hrs)',
            self::OVERTIME_MON_SAT_AFTER_2H => 'Overtime Mon–Sat (after 2 hrs)',
            self::OVERTIME_SUNDAY => 'Overtime Sunday',
            self::OVERTIME_PUBLIC_HOLIDAY => 'Overtime public holiday',
            self::ALLOWANCE => 'Allowance',
            self::SICK_LEAVE_ACCRUAL => 'Sick leave accrued',
            self::ANNUAL_LEAVE_ACCRUAL => 'Annual leave accrued',
            self::SICK_LEAVE_TAKEN => 'Sick leave taken',
            self::ANNUAL_LEAVE_TAKEN => 'Annual leave taken',
            self::LEAVE_TAKEN => 'Leave taken',
            self::UNPAID_LEAVE_TAKEN => 'Unpaid leave taken',
            default => ucfirst(str_replace('_', ' ', $rateType)),
        };
    }

    public static function employmentTypeLabel(?string $type): string
    {
        return match ($type) {
            'full_time' => 'Full-time',
            'part_time' => 'Part-time',
            'casual' => 'Casual',
            default => '—',
        };
    }

    public static function awardLevelLabel(?string $level): string
    {
        return match ($level) {
            'level_1' => 'Level 1 Cleaner',
            'level_2' => 'Level 2 Cleaner',
            default => '—',
        };
    }

    /**
     * @return list<string>
     */
    public static function employmentTypes(): array
    {
        return ['full_time', 'part_time', 'casual'];
    }

    /**
     * @return list<string>
     */
    public static function awardLevels(): array
    {
        return ['level_1', 'level_2'];
    }
}
