<?php

namespace Tests\Unit;

use App\Support\DisplayTimezone;
use App\Support\IncidentReportForm;
use Tests\TestCase;

class IncidentReportFormTest extends TestCase
{
    public function test_prepare_rejects_a_form_that_skips_required_fields(): void
    {
        $result = IncidentReportForm::prepare(['site' => ''], ['Everton Plaza Shopping Centre']);

        $this->assertSame('Site is required.', $result['error']);
    }

    public function test_prepare_keeps_the_drawn_signature_for_the_admin_page(): void
    {
        $when = DisplayTimezone::now()->subHour();
        $answers = [
            'site' => 'Everton Plaza Shopping Centre',
            'signature' => json_encode([
                'width' => 300,
                'height' => 160,
                'strokes' => [[['x' => 10, 'y' => 20], ['x' => 40, 'y' => 55]]],
            ]),
            'occurred_on' => $when->toDateString(),
            'occurred_time' => $when->format('H:i'),
            'reported_on' => $when->toDateString(),
            'reported_time' => $when->format('H:i'),
            'location' => 'Dock',
            'description' => 'Wet floor',
            'incident_type' => 'safety_hazard',
            'person_age' => '30',
            'outcomes' => ['No injury'],
            'declaration_first' => 'Alex',
            'declaration_last' => 'Morgan',
            'declaration_on' => $when->toDateString(),
        ];
        foreach ([
            'reported_to_first', 'reported_to_last', 'witnesses', 'person_type', 'person_first', 'person_last',
            'addr_line1', 'employment_type', 'phone_area', 'phone_number', 'gender', 'injured', 'first_aid',
            'medical_treatment', 'property_equipment_damage', 'injury_aggravated', 'before_incident',
            'personally_experienced', 'ongoing_concern', 'impact_description', 'continued_working',
            'safety_risk', 'trained', 'procedure_followed', 'cctv', 'weather', 'property_lost',
        ] as $key) {
            $answers[$key] = $answers[$key] ?? 'Yes';
        }

        $result = IncidentReportForm::prepare($answers, ['Everton Plaza Shopping Centre']);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(10.0, $result['details']['signature_strokes'][0][0]['x']);
        $this->assertSame(300, $result['details']['signature_width']);
        $declaration = collect($result['details']['sections'])->firstWhere('title', 'EMPLOYEE DECLARATION');
        $signed = collect($declaration['rows'])->firstWhere('label', 'Signature');
        $this->assertNull($signed);
    }

    public function test_prepare_stores_signature_strokes_sent_beside_the_answers(): void
    {
        $when = DisplayTimezone::now()->subHour();
        $result = IncidentReportForm::prepare([
            'site' => 'Everton Plaza Shopping Centre',
            'location' => 'Dock',
            'occurred_on' => $when->toDateString(),
            'occurred_time' => $when->format('H:i'),
            'reported_on' => $when->toDateString(),
            'reported_time' => $when->format('H:i'),
            'reported_to_first' => 'Sam',
            'reported_to_last' => 'Lee',
            'witnesses' => 'N/A',
            'person_type' => 'Employee',
            'person_first' => 'Alex',
            'person_last' => 'Morgan',
            'addr_line1' => '1 Test Street',
            'employment_type' => 'Casual',
            'phone_area' => '+61',
            'phone_number' => '30001111',
            'gender' => 'Female',
            'person_age' => '29',
            'injured' => 'No',
            'first_aid' => 'Not required',
            'medical_treatment' => 'No',
            'incident_type' => 'safety_hazard',
            'property_equipment_damage' => 'No',
            'injury_aggravated' => 'Not applicable',
            'before_incident' => 'Walking',
            'description' => 'Wet floor',
            'personally_experienced' => 'I saw it.',
            'ongoing_concern' => 'No',
            'outcomes' => ['No injury'],
            'impact_description' => 'None',
            'continued_working' => 'Yes',
            'safety_risk' => 'Yes',
            'trained' => 'Yes',
            'procedure_followed' => 'Yes',
            'cctv' => 'Unknown',
            'weather' => 'Not Applicable (Indoor Incident)',
            'property_lost' => 'No',
            'declaration_first' => 'Ifiot',
            'declaration_last' => 'Hsjsh',
            'declaration_on' => $when->toDateString(),
            'signature' => 'drawn',
            'signature_width' => 280,
            'signature_height' => 160,
            'signature_strokes' => [[['x' => 12, 'y' => 40], ['x' => 80, 'y' => 90]]],
        ], ['Everton Plaza Shopping Centre']);

        $this->assertArrayNotHasKey('error', $result);
        $this->assertSame(12.0, $result['details']['signature_strokes'][0][0]['x']);
        $this->assertSame(280, $result['details']['signature_width']);
        $declaration = collect($result['details']['sections'])->firstWhere('title', 'EMPLOYEE DECLARATION');
        $this->assertNull(collect($declaration['rows'])->firstWhere('label', 'Signature'));
    }

    public function test_prepare_builds_admin_sections_from_a_complete_form(): void
    {
        $when = DisplayTimezone::now()->subHour();
        $today = $when->toDateString();
        $clock = $when->format('H:i');
        $answers = [
            'site' => 'Everton Plaza Shopping Centre',
            'location' => 'Loading dock',
            'occurred_on' => $today,
            'occurred_time' => $clock,
            'reported_on' => $today,
            'reported_time' => $clock,
            'reported_to_first' => 'Sam',
            'reported_to_last' => 'Lee',
            'witnesses' => 'N/A',
            'person_type' => 'Employee',
            'person_first' => 'Alex',
            'person_last' => 'Morgan',
            'addr_line1' => '1 Test Street',
            'employment_type' => 'Casual',
            'phone_area' => '+61',
            'phone_number' => '30001111',
            'gender' => 'Female',
            'person_age' => '29',
            'injured' => 'No',
            'first_aid' => 'Not required',
            'medical_treatment' => 'No',
            'incident_type' => 'safety_hazard',
            'property_equipment_damage' => 'No',
            'injury_aggravated' => 'Not applicable',
            'before_incident' => 'Walking to the dock',
            'description' => 'Wet floor near the entrance.',
            'personally_experienced' => 'I saw the wet floor.',
            'ongoing_concern' => 'No',
            'outcomes' => ['No injury'],
            'impact_description' => 'No obvious physical injury reported.',
            'continued_working' => 'Yes',
            'safety_risk' => 'Yes',
            'trained' => 'Yes',
            'procedure_followed' => 'Yes',
            'cctv' => 'Unknown',
            'weather' => 'Not Applicable (Indoor Incident)',
            'property_lost' => 'No',
            'declaration_first' => 'Alex',
            'declaration_last' => 'Morgan',
            'declaration_on' => $today,
            'signature' => 'drawn',
        ];

        $result = IncidentReportForm::prepare($answers, ['Everton Plaza Shopping Centre']);

        $this->assertArrayNotHasKey('error', $result);
        $person = collect($result['details']['sections'])->firstWhere('title', 'PERSON INVOLVED');
        $phone = collect($person['rows'])->firstWhere('label', 'Phone number');
        $this->assertSame('+61 30001111', $phone['value']);
        $this->assertSame('safety_hazard', $result['record']['incident_type']);
        $this->assertSame('Everton Plaza Shopping Centre', $result['record']['site_name']);
        $titles = array_column($result['details']['sections'], 'title');
        $this->assertContains('INCIDENT DETAILS', $titles);
        $this->assertContains('WHAT HAPPENED?', $titles);
        $this->assertContains('EMPLOYEE DECLARATION', $titles);
        $declaration = collect($result['details']['sections'])->firstWhere('title', 'EMPLOYEE DECLARATION');
        $signed = collect($declaration['rows'])->firstWhere('label', 'Signature');
        $this->assertSame('Signed in the app', $signed['value']);
    }
}
