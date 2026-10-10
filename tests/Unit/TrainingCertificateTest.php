<?php

namespace Tests\Unit;

use App\Models\TrainingCertificate;
use App\Support\DisplayTimezone;
use App\Support\TrainingAssignmentNotice;
use App\Support\TrainingCertificates;
use Carbon\Carbon;
use Tests\TestCase;

class TrainingCertificateTest extends TestCase
{
    public function test_a_certificate_is_issued_when_the_training_is_passed(): void
    {
        $this->assertTrue(TrainingCertificates::shouldIssue(true));
        $this->assertFalse(TrainingCertificates::shouldIssue(false));
        $this->assertFalse(TrainingCertificates::shouldIssue(null));
    }

    public function test_completion_date_uses_the_business_timezone(): void
    {
        $submitted = Carbon::parse('2026-10-09 15:30:00', 'UTC');

        $completed = TrainingCertificates::completionDate($submitted);

        $this->assertNotNull($completed);
        $this->assertSame('2026-10-10', $completed->toDateString());
        $this->assertSame(DisplayTimezone::name(), $completed->getTimezone()->getName());
    }

    public function test_refresher_date_is_the_completion_date_plus_the_validity_months(): void
    {
        $completed = Carbon::parse('2026-10-10', DisplayTimezone::name())->startOfDay();

        $refresher = TrainingCertificates::refresherDate($completed, 12);

        $this->assertNotNull($refresher);
        $this->assertSame('2027-10-10', $refresher->toDateString());
        $this->assertNull(TrainingCertificates::refresherDate($completed, null));
        $this->assertNull(TrainingCertificates::refresherDate($completed, 0));
        $this->assertNull(TrainingCertificates::refresherDate(null, 12));
    }

    public function test_reference_numbers_identify_the_completion_year(): void
    {
        $completed = Carbon::parse('2026-04-02', DisplayTimezone::name());
        $reference = TrainingCertificates::makeReference($completed);

        $this->assertMatchesRegularExpression('/^TC-2026-[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $reference);
        $this->assertSame(0, preg_match('/[01IO]/', substr($reference, -6)));
    }

    public function test_an_assigned_module_with_a_due_date_asks_the_employee_to_complete_it(): void
    {
        $copy = TrainingAssignmentNotice::message('Workplace Safety', '2026-10-20');

        $this->assertSame('Training assigned', $copy['title']);
        $this->assertSame(
            'Workplace Safety has been assigned. Please complete it by 20 October 2026.',
            $copy['body'],
        );
    }

    public function test_the_organization_the_employee_signed_in_to_is_printed_on_the_certificate(): void
    {
        $this->assertSame(
            'Blue Green Facility Services',
            TrainingCertificates::companyName('Blue Green Facility Services', null),
        );
        $this->assertSame(
            'Construct Concepts',
            TrainingCertificates::companyName('Construct Concepts', null),
        );
        $this->assertSame(
            'Aid and Able Services',
            TrainingCertificates::companyName('Aid and Able Services', null),
        );
    }

    public function test_certificate_payload_includes_the_completion_record(): void
    {
        $certificate = new TrainingCertificate([
            'reference_number' => 'TC-2026-AB23CD',
            'employee_name' => 'Alex Morgan',
            'training_name' => 'Manual Handling',
            'company_name' => 'Bluegreen Cleaning',
            'completed_on' => '2026-10-10',
            'expires_on' => '2027-10-10',
        ]);

        $this->assertSame([
            'reference_number' => 'TC-2026-AB23CD',
            'employee_name' => 'Alex Morgan',
            'training_name' => 'Manual Handling',
            'company_name' => 'Bluegreen Cleaning',
            'completed_on' => '2026-10-10',
            'completed_on_label' => '10 October 2026',
            'expires_on' => '2027-10-10',
            'expires_on_label' => '10 October 2027',
            'signature' => null,
        ], TrainingCertificates::present($certificate));
    }

    public function test_a_drawn_signature_is_kept_for_the_certificate(): void
    {
        $signature = TrainingCertificates::normalizeSignature([
            'width' => 300,
            'height' => 160,
            'strokes' => [[['x' => 12.2, 'y' => 40], ['x' => 80, 'y' => 90.4]]],
        ]);

        $this->assertTrue($signature['signed']);
        $this->assertSame(300, $signature['width']);
        $this->assertSame(12.2, $signature['strokes'][0][0]['x']);
        $this->assertSame(90.4, $signature['strokes'][0][1]['y']);
        $this->assertFalse(TrainingCertificates::normalizeSignature(['strokes' => []])['signed']);
    }
}
