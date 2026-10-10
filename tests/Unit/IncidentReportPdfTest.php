<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\IncidentReport;
use App\Support\IncidentReportPdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentReportPdfTest extends TestCase
{
    public function test_document_includes_sections_signature_and_review(): void
    {
        $report = $this->report();
        $report->id = 42;
        $report->admin_note = "Follow up with the site lead.\nCheck the dock.";
        $report->reviewed_by = 'Jordan Lee';
        $report->reviewed_at = Carbon::parse('2026-10-08 04:00:00', 'UTC');
        $report->status = IncidentReport::STATUS_ACKNOWLEDGED;

        $document = IncidentReportPdf::document($report, 'BluGreen');

        $this->assertSame('IR-00042', $document['reference']);
        $this->assertSame('IR-00042.pdf', IncidentReportPdf::filename($report));
        $this->assertSame('Alex Morgan', $document['reporter']);
        $this->assertSame('Acknowledged', $document['status_label']);
        $this->assertSame('Jordan Lee', $document['review']['reviewed_by']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $document['logo']);

        $declaration = collect($document['sections'])->firstWhere('title', 'EMPLOYEE DECLARATION');
        $this->assertNotNull($declaration);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $declaration['signature']);
        $this->assertNotContains('Signature', array_column($declaration['rows'], 'label'));
        $this->assertContains('Date', array_column($declaration['rows'], 'label'));
    }

    public function test_photos_are_embedded_and_missing_files_are_named(): void
    {
        Storage::fake('incident_attachments');
        $png = imagecreatetruecolor(8, 8);
        $blue = imagecolorallocate($png, 0, 61, 122);
        imagefilledrectangle($png, 0, 0, 7, 7, $blue);
        ob_start();
        imagepng($png);
        $binary = ob_get_clean();
        imagedestroy($png);
        Storage::disk('incident_attachments')->put('dock.png', $binary);

        $report = $this->report();
        $details = $report->details;
        $details['files'] = [
            ['path' => 'dock.png', 'name' => 'dock.png', 'mime' => 'image/png', 'group' => 'incident'],
            ['path' => 'missing.png', 'name' => 'gone.png', 'mime' => 'image/png', 'group' => 'incident'],
        ];
        $details['sections'][] = [
            'title' => 'ATTACHMENTS / EVIDENCE',
            'rows' => [['label' => 'CCTV cameras covering the area', 'value' => 'Unknown']],
        ];
        $report->details = $details;

        $document = IncidentReportPdf::document($report, 'BluGreen');
        $photos = collect($document['sections'])->firstWhere('title', 'ATTACHMENTS / EVIDENCE')['photos'];

        $this->assertCount(2, $photos);
        $this->assertSame('dock.png', $photos[0]['name']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $photos[0]['src']);
        $this->assertSame('gone.png', $photos[1]['name']);
        $this->assertNull($photos[1]['src']);
    }

    public function test_render_produces_a_pdf_for_a_report_and_a_register(): void
    {
        $report = $this->report();
        $report->id = 7;

        $single = IncidentReportPdf::render($report, 'BluGreen');
        $register = IncidentReportPdf::renderRegister([$report], 'BluGreen', 'open');

        $this->assertStringStartsWith('%PDF', $single);
        $this->assertGreaterThan(2000, strlen($single));
        $this->assertStringStartsWith('%PDF', $register);
        $this->assertSame('incident-register-open.pdf', IncidentReportPdf::registerFilename('open'));
        $this->assertSame('incident-register-open.pdf', IncidentReportPdf::registerFilename('nope'));

        $empty = IncidentReportPdf::registerDocument([], 'BluGreen', 'resolved');
        $this->assertSame(0, $empty['total']);
        $this->assertSame('Resolved reports', $empty['filter_label']);
        $this->assertSame([], $empty['rows']);
    }

    private function report(): IncidentReport
    {
        $employee = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'email' => 'alex@example.com',
        ]);

        $report = new IncidentReport([
            'incident_type' => 'near_miss',
            'site_name' => 'Everton Plaza',
            'location' => 'Loading dock',
            'occurred_at' => Carbon::parse('2026-10-08 01:30:00', 'UTC'),
            'summary' => 'Slipped on a wet floor.',
            'status' => IncidentReport::STATUS_NEW,
            'details' => [
                'signature_width' => 320,
                'signature_height' => 160,
                'signature_strokes' => [
                    [
                        ['x' => 20, 'y' => 80],
                        ['x' => 80, 'y' => 40],
                        ['x' => 140, 'y' => 90],
                    ],
                ],
                'sections' => [
                    [
                        'title' => 'WHAT HAPPENED?',
                        'rows' => [
                            ['label' => 'Description of the incident', 'value' => "Wet floor near the dock.\nNo injury."],
                        ],
                    ],
                    [
                        'title' => 'EMPLOYEE DECLARATION',
                        'rows' => [
                            ['label' => 'Full name', 'value' => 'Alex Morgan'],
                            ['label' => 'Signature', 'value' => 'Signed in the app'],
                            ['label' => 'Date', 'value' => '2026-10-08'],
                        ],
                    ],
                ],
            ],
        ]);
        $report->created_at = Carbon::parse('2026-10-08 01:40:00', 'UTC');
        $report->setRelation('employee', $employee);

        return $report;
    }
}
