<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\PayrollRunExport;
use Tests\TestCase;

class PayrollRunExportTest extends TestCase
{
    public function test_csv_and_pdf_include_each_job_title_line_and_the_period_total(): void
    {
        $employee = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'employee_code' => 'E-14',
            'email' => 'alex@example.com',
        ]);
        $skipped = new Employee([
            'full_legal_name' => 'Sam Lee',
            'employee_code' => '',
            'email' => 'sam@example.com',
        ]);

        $rows = [
            [
                'employee' => $employee,
                'total_hours' => 8,
                'total_amount' => 248.16,
                'scheduled_hours' => 8,
                'roster_variance' => 'Matches schedule',
                'skipped_reason' => null,
                'lines' => [
                    ['rate_type' => 'job_title_1', 'label' => 'CAS1', 'hours' => 4, 'rate' => 32.31, 'amount' => 129.24],
                    ['rate_type' => 'job_title_2', 'label' => 'PT1', 'hours' => 4, 'rate' => 29.73, 'amount' => 118.92],
                ],
            ],
            [
                'employee' => $skipped,
                'total_hours' => 0,
                'total_amount' => 0,
                'scheduled_hours' => 6,
                'roster_variance' => '—',
                'skipped_reason' => 'No hours to pay',
                'lines' => [],
            ],
        ];

        $document = PayrollRunExport::document('BluGreen', '2026-07-06', '2026-07-19', 'draft', $rows);
        $this->assertSame('Draft', $document['status']);
        $this->assertSame(1, $document['included_count']);
        $this->assertSame(248.16, $document['total_amount']);
        $this->assertSame('CAS1', $document['employees'][0]['lines'][0]['label']);
        $this->assertSame('PT1', $document['employees'][0]['lines'][1]['label']);

        $csv = PayrollRunExport::csv('BluGreen', '2026-07-06', '2026-07-19', 'draft', $rows);
        $this->assertStringContainsString('BluGreen', $csv);
        $this->assertStringContainsString('CAS1', $csv);
        $this->assertStringContainsString('PT1', $csv);
        $this->assertStringContainsString('248.16', $csv);
        $this->assertStringContainsString('Not included', $csv);
        $this->assertSame('pay-run-2026-07-06-to-2026-07-19.csv', PayrollRunExport::filename('2026-07-06', '2026-07-19', 'csv'));

        $html = view('admin.payroll.pdf', $document)->render();
        $this->assertStringContainsString('CAS1', $html);
        $this->assertStringContainsString('$129.24', $html);
        $this->assertStringContainsString('NOT INCLUDED', $html);
        $this->assertStringContainsString('No hours to pay', $html);
        $this->assertStringContainsString('$248.16', $html);
        $this->assertStringContainsString('PAYROLL STATEMENT', $html);

        $logo = (new \ReflectionMethod(PayrollRunExport::class, 'logoDataUri'))->invoke(null);
        $this->assertIsString($logo);
        $this->assertStringStartsWith('data:image/png;base64,', $logo);
        $branded = view('admin.payroll.pdf', array_merge($document, ['logo' => $logo]))->render();
        $this->assertStringContainsString($logo, $branded);

        $pdf = PayrollRunExport::render('BluGreen', '2026-07-06', '2026-07-19', 'finalized', $rows);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(8000, strlen($pdf));
    }
}
