<?php

namespace Tests\Unit;

use App\Support\AdminPayroll;
use App\Support\PayrollAwardRateDefaults;
use App\Support\PayrollRateTypes;
use Carbon\Carbon;
use Tests\TestCase;

class AdminPayrollTest extends TestCase
{
    public function test_fortnight_end_is_thirteen_days_after_start(): void
    {
        $start = '2025-07-07';
        $this->assertSame('2025-07-20', AdminPayroll::fortnightEndForStart($start));
    }

    public function test_default_rates_include_all_employment_types(): void
    {
        $all = PayrollAwardRateDefaults::all();
        foreach (PayrollRateTypes::employmentTypes() as $type) {
            $this->assertArrayHasKey($type, $all);
            foreach (PayrollRateTypes::awardLevels() as $level) {
                $this->assertArrayHasKey($level, $all[$type]);
                $this->assertCount(count(PayrollRateTypes::awardRateKeys()), $all[$type][$level]);
            }
        }
    }

    public function test_normalize_fortnight_start_snaps_to_monday_pair(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-07-10', 'Australia/Sydney'));
        $normalized = AdminPayroll::normalizeFortnightStart('2025-07-10');
        $this->assertSame(Carbon::MONDAY, (int) Carbon::parse($normalized)->dayOfWeek);
        Carbon::setTestNow();
    }
}
