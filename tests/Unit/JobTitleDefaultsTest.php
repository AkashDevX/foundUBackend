<?php

namespace Tests\Unit;

use App\Support\JobTitleDefaults;
use App\Support\PayrollRateTypes;
use Tests\TestCase;

class JobTitleDefaultsTest extends TestCase
{
    public function test_band_prefix_and_default_names(): void
    {
        $this->assertSame('CAS1', JobTitleDefaults::bandPrefix('casual', 'level_1'));
        $this->assertSame('FT2', JobTitleDefaults::bandPrefix('full_time', 'level_2'));
        $this->assertSame('PT1', JobTitleDefaults::bandPrefix('part_time', 'level_1'));

        $this->assertSame('CAS1', JobTitleDefaults::defaultName('casual', 'level_1', PayrollRateTypes::WEEKDAY_ORDINARY));
        $this->assertSame('CAS1 (Saturday)', JobTitleDefaults::defaultName('casual', 'level_1', PayrollRateTypes::SATURDAY));
        $this->assertSame('CAS1 (Penalty Rates)', JobTitleDefaults::defaultName('casual', 'level_1', PayrollRateTypes::WEEKDAY_PENALTY));
        $this->assertSame('CAS1 (Public Holiday)', JobTitleDefaults::defaultName('casual', 'level_1', PayrollRateTypes::PUBLIC_HOLIDAY));
        $this->assertSame('CAS1 (Sunday)', JobTitleDefaults::defaultName('casual', 'level_1', PayrollRateTypes::SUNDAY));
        $this->assertSame('FT2 (Midnight Shift)', JobTitleDefaults::defaultName('full_time', 'level_2', PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT));
        $this->assertSame('FT2 (OT Mon–Sat First 2h)', JobTitleDefaults::defaultName('full_time', 'level_2', PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H));
    }

    public function test_seed_set_matches_payroll_document(): void
    {
        $this->assertSame([
            ['full_time', 'level_1'],
            ['full_time', 'level_2'],
            ['part_time', 'level_1'],
            ['part_time', 'level_2'],
            ['casual', 'level_1'],
        ], JobTitleDefaults::seedBands());

        $this->assertSame(PayrollRateTypes::awardRateKeys(), JobTitleDefaults::seedRateKinds());
    }

    public function test_wages_match_payroll_document(): void
    {
        $this->assertSame(26.70, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::WEEKDAY_ORDINARY));
        $this->assertSame(30.71, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::WEEKDAY_PENALTY));
        $this->assertSame(34.71, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT));
        $this->assertSame(40.05, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::SATURDAY));
        $this->assertSame(53.40, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::SUNDAY));
        $this->assertSame(66.75, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::PUBLIC_HOLIDAY));
        $this->assertSame(40.05, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H));
        $this->assertSame(53.40, JobTitleDefaults::defaultWage('full_time', 'level_2', PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H));

        $this->assertSame(32.31, JobTitleDefaults::defaultWage('casual', 'level_1', PayrollRateTypes::WEEKDAY_ORDINARY));
        $this->assertSame(36.19, JobTitleDefaults::defaultWage('casual', 'level_1', PayrollRateTypes::WEEKDAY_PENALTY));
        $this->assertSame(45.24, JobTitleDefaults::defaultWage('casual', 'level_1', PayrollRateTypes::SATURDAY));
        $this->assertSame(58.16, JobTitleDefaults::defaultWage('casual', 'level_1', PayrollRateTypes::SUNDAY));
        $this->assertSame(71.09, JobTitleDefaults::defaultWage('casual', 'level_1', PayrollRateTypes::PUBLIC_HOLIDAY));

        $this->assertSame(29.73, JobTitleDefaults::defaultWage('part_time', 'level_1', PayrollRateTypes::WEEKDAY_ORDINARY));
        $this->assertSame(25.85, JobTitleDefaults::defaultWage('full_time', 'level_1', PayrollRateTypes::WEEKDAY_ORDINARY));
    }

    public function test_legacy_band_name_still_available(): void
    {
        $this->assertSame('Casual Cleaner Level 1', JobTitleDefaults::legacyBandName('casual', 'level_1'));
        $this->assertSame('Full-time Cleaner Level 2', JobTitleDefaults::legacyBandName('full_time', 'level_2'));
    }
}
