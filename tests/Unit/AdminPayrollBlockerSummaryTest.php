<?php

namespace Tests\Unit;

use App\Support\AdminPayroll;
use Tests\TestCase;

class AdminPayrollBlockerSummaryTest extends TestCase
{
    public function test_summarize_blockers_lists_each_skip_reason(): void
    {
        $employee = new \App\Models\Employee(['email' => 'a@test.com']);

        $summary = AdminPayroll::summarizeBlockers([
            [
                'employee' => $employee,
                'skipped_reason' => 'Employment type not set',
                'total_hours' => 0,
            ],
            [
                'employee' => $employee,
                'skipped_reason' => 'Shifts in this pay period are not approved yet. Approve them under Time clock records.',
                'total_hours' => 0,
            ],
        ]);

        $this->assertStringContainsString('Employment type not set', $summary);
        $this->assertStringContainsString('Shifts in this pay period are not approved yet. Approve them under Time clock records.', $summary);
        $this->assertStringNotContainsString('PAYROLL_REQUIRE_APPROVED_TIMESHEETS', $summary);
        $this->assertStringNotContainsString('.env', $summary);
    }
}
