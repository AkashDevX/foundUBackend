<?php

namespace Tests\Unit;

use App\Support\PayrollLineTotals;
use App\Support\PayrollRateTypes;
use Tests\TestCase;

class PayrollLineTotalsTest extends TestCase
{
    public function test_excludes_accruals_from_gross_and_shows_earnings_calculation_buckets(): void
    {
        $summary = PayrollLineTotals::summarize([
            [
                'rate_type' => PayrollRateTypes::WEEKDAY_ORDINARY,
                'description' => 'Mon–Fri ordinary (6am–6pm)',
                'hours' => 38,
                'rate' => 26.70,
                'amount' => 1014.60,
                'sort_order' => 0,
            ],
            [
                'rate_type' => PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H,
                'label' => 'Overtime Mon–Sat (first 2 hrs)',
                'hours' => 2,
                'rate' => 40.05,
                'amount' => 80.10,
                'sort_order' => 1,
            ],
            [
                'rate_type' => PayrollRateTypes::ALLOWANCE,
                'label' => 'Travel allowance',
                'hours' => 0,
                'rate' => 25,
                'amount' => 25,
                'sort_order' => 2,
            ],
            [
                'rate_type' => PayrollRateTypes::SICK_LEAVE_TAKEN,
                'label' => 'Sick leave taken · Jul 10, 2025',
                'hours' => 7.6,
                'rate' => 26.70,
                'amount' => 202.92,
                'sort_order' => 3,
            ],
            [
                'rate_type' => PayrollRateTypes::SICK_LEAVE_ACCRUAL,
                'label' => 'Sick leave accrued',
                'hours' => 1.09,
                'rate' => 26.70,
                'amount' => 29.10,
                'sort_order' => 4,
            ],
            [
                'rate_type' => PayrollRateTypes::ANNUAL_LEAVE_ACCRUAL,
                'label' => 'Annual leave accrued',
                'hours' => 1.09,
                'rate' => 26.70,
                'amount' => 29.10,
                'sort_order' => 5,
            ],
            [
                'rate_type' => PayrollRateTypes::UNPAID_LEAVE_TAKEN,
                'label' => 'Unpaid leave taken · Jul 11, 2025',
                'hours' => 4,
                'rate' => 0,
                'amount' => 0,
                'sort_order' => 6,
            ],
        ]);

        $this->assertSame(1322.62, $summary['gross_pay']);
        $this->assertSame(40.0, $summary['worked_hours']);
        $this->assertSame(1014.60, $summary['ordinary_amount']);
        $this->assertSame(80.10, $summary['overtime_amount']);
        $this->assertSame(25.0, $summary['allowance_amount']);
        $this->assertSame(202.92, $summary['paid_leave_amount']);
        $this->assertSame(7.6, $summary['paid_leave_hours']);
        $this->assertSame(4.0, $summary['unpaid_leave_hours']);
        $this->assertSame(58.20, $summary['accruals_value']);
        $this->assertFalse($summary['deductions_recorded']);
        $this->assertNull($summary['net_pay']);
        $this->assertCount(4, $summary['earnings']);
        $this->assertCount(2, $summary['accruals']);
        $this->assertCount(1, $summary['unpaid_leave']);
    }

    public function test_deduction_lines_produce_net_pay(): void
    {
        $summary = PayrollLineTotals::summarize([
            [
                'rate_type' => PayrollRateTypes::WEEKDAY_ORDINARY,
                'label' => 'Ordinary',
                'hours' => 10,
                'rate' => 20,
                'amount' => 200,
                'sort_order' => 0,
            ],
            [
                'rate_type' => 'payg',
                'label' => 'PAYG withholding',
                'hours' => 0,
                'rate' => 0,
                'amount' => 40,
                'sort_order' => 1,
            ],
        ]);

        $this->assertTrue($summary['deductions_recorded']);
        $this->assertSame(200.0, $summary['gross_pay']);
        $this->assertSame(40.0, $summary['deductions_total']);
        $this->assertSame(160.0, $summary['net_pay']);
    }
}
