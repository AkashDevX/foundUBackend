<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\IncidentReport;
use Carbon\Carbon;
use Tests\TestCase;

class IncidentReportPresentationTest extends TestCase
{
    public function test_presentation_includes_incident_and_person_and_skips_blanks(): void
    {
        $employee = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'email' => 'alex@example.com',
        ]);

        $report = new IncidentReport([
            'incident_type' => 'injury',
            'site_name' => 'Everton Plaza',
            'location' => 'Loading dock',
            'occurred_at' => Carbon::parse('2026-10-08 01:30:00', 'UTC'),
            'summary' => 'Slipped on a wet floor.',
            'witnesses' => 'N/A',
            'reported_to' => 'Site manager',
            'status' => IncidentReport::STATUS_NEW,
            'details' => [
                'person_type' => 'employee',
                'person_name' => 'Alex Morgan',
                'person_phone' => '0400000000',
                'person_address' => '1 Test Street',
                'employment_type' => 'casual',
                'person_gender' => 'female',
                'person_age' => '29',
                'injured' => 'yes',
                'first_aid' => 'yes',
                'medical_treatment' => 'not_known',
                'is_under_18' => 'no',
                'cctv' => 'unknown',
                'incident_type_other' => '',
            ],
        ]);
        $report->created_at = Carbon::parse('2026-10-08 01:40:00', 'UTC');
        $report->setRelation('employee', $employee);

        $this->assertSame('Alex Morgan', $report->reporterName());
        $this->assertSame('Injury', $report->typeLabel());
        $this->assertSame('New', $report->statusLabel());

        $sections = $report->presentationSections();
        $titles = array_column($sections, 'title');

        $this->assertContains('Incident', $titles);
        $this->assertContains('Person involved', $titles);
        $this->assertNotContains('Accompanying adult', $titles);
        $this->assertNotContains('Workplace behaviour', $titles);

        $person = collect($sections)->firstWhere('title', 'Person involved');
        $labels = array_column($person['rows'], 'label');
        $this->assertContains('Injured', $labels);
        $this->assertNotContains('Name of accompanying adult', $labels);

        $injured = collect($person['rows'])->firstWhere('label', 'Injured');
        $this->assertSame('Yes', $injured['value']);
    }

    public function test_other_type_and_behaviour_sections(): void
    {
        $report = new IncidentReport([
            'incident_type' => 'harassment',
            'site_name' => 'Flagstone Central',
            'location' => 'Office',
            'summary' => 'Repeated comments.',
            'details' => [
                'is_under_18' => 'yes',
                'accompanied_by_adult' => 'yes',
                'accompanying_adult_name' => 'Sam Lee',
                'behaviour_ongoing' => 'yes',
                'cctv' => 'no',
            ],
        ]);

        $titles = array_column($report->presentationSections(), 'title');
        $this->assertContains('Accompanying adult', $titles);
        $this->assertContains('Workplace behaviour', $titles);
        $this->assertSame('Harassment or bullying concern', $report->typeLabel());
    }
}
