<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\EmployeeProfilePdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EmployeeProfilePdfTest extends TestCase
{
    public function test_document_includes_profile_sections_and_masks_the_bank_account(): void
    {
        $employee = new Employee([
            'public_id' => '11111111-2222-3333-4444-555555555555',
            'employee_code' => 'EMP-14',
            'full_legal_name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'phone' => '0400000000',
            'employment_status' => 'active',
            'date_of_birth' => '1990-04-02',
            'address' => '1 Test Street',
            'emergency_contact_name' => 'Sam Lee',
            'emergency_contact_phone' => '0400111222',
            'department' => 'Night crew',
            'bank_account_name' => 'Alex Morgan',
            'bank_name' => 'Example Bank',
            'bank_branch_code' => '062-000',
            'bank_account_number' => '123456789012',
            'weekly_availability_json' => [
                'mon' => ['morning' => true],
                'tue' => ['morning' => true, 'evening' => true],
            ],
            'id_documents_json' => [[
                'documentType' => 'Passport',
                'storage_path' => 'acme/passport.jpg',
                'expiry' => '2030-01-15',
            ]],
        ]);
        $employee->created_at = Carbon::parse('2026-03-01 02:00:00', 'UTC');

        $document = EmployeeProfilePdf::document($employee, 'BluGreen');

        $this->assertSame('Alex Morgan', $document['name']);
        $this->assertSame('Active', $document['status_label']);
        $this->assertSame('Alex-Morgan-EMP-14.pdf', EmployeeProfilePdf::filename($employee));

        $identity = collect($document['sections'])->firstWhere('title', 'IDENTITY & PERSONAL');
        $this->assertSame('02/04/1990', collect($identity['rows'])->firstWhere('label', 'Date of birth')['value']);

        $availability = collect($document['sections'])->firstWhere('title', 'WORK ELIGIBILITY & AVAILABILITY');
        $this->assertStringContainsString('Monday — Morning', collect($availability['rows'])->firstWhere('label', 'Weekly availability')['value']);
        $this->assertStringContainsString('Tuesday — Morning and Evening', collect($availability['rows'])->firstWhere('label', 'Weekly availability')['value']);

        $checks = collect($document['sections'])->firstWhere('title', 'ID & CHECKS');
        $this->assertStringContainsString('Passport', collect($checks['rows'])->firstWhere('label', 'ID documents')['value']);
        $this->assertStringContainsString('On file', collect($checks['rows'])->firstWhere('label', 'ID documents')['value']);
        $this->assertSame([], $checks['images'] ?? []);

        $payroll = collect($document['sections'])->firstWhere('title', 'PAYROLL');
        $this->assertSame('XXXXXXXXXX9012', collect($payroll['rows'])->firstWhere('label', 'Account number')['value']);
        $this->assertNotContains('123456789012', array_column($payroll['rows'], 'value'));
    }

    public function test_profile_photo_is_embedded_when_the_file_exists(): void
    {
        Storage::fake('employee_registration');
        $png = imagecreatetruecolor(12, 12);
        $blue = imagecolorallocate($png, 0, 61, 122);
        imagefilledrectangle($png, 0, 0, 11, 11, $blue);
        ob_start();
        imagepng($png);
        $binary = ob_get_clean();
        imagedestroy($png);
        Storage::disk('employee_registration')->put('acme/photo.png', $binary);

        $employee = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'profile_photo_path' => 'acme/photo.png',
            'employment_status' => 'pending',
        ]);

        $document = EmployeeProfilePdf::document($employee, 'BluGreen');

        $this->assertStringStartsWith('data:image/png;base64,', (string) $document['photo']);
        $this->assertSame('Pending', $document['status_label']);
    }

    public function test_every_available_image_is_embedded_and_missing_files_are_skipped(): void
    {
        Storage::fake('employee_registration');
        $disk = Storage::disk('employee_registration');
        $disk->put('acme/visa.jpg', $this->jpeg());
        $disk->put('acme/passport.jpg', $this->jpeg());
        $disk->put('acme/licence-back.png', $this->png());
        $disk->put('acme/police.jpg', $this->jpeg());
        $disk->put('acme/fit.png', $this->png());
        $disk->put('acme/forklift.jpg', $this->jpeg());
        $disk->put('acme/insurance.jpg', $this->jpeg());
        $disk->put('acme/vehicle.jpg', $this->jpeg());
        $disk->put('acme/fit.pdf', '%PDF-1.4 fake');

        $withImages = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'visa_document_path' => 'acme/visa.jpg',
            'id_documents_json' => [
                ['documentType' => 'Passport', 'storage_path' => 'acme/passport.jpg'],
                [
                    'documentType' => "Driver's Licence",
                    'storage_path' => 'acme/missing-front.jpg',
                    'back_storage_path' => 'acme/licence-back.png',
                ],
            ],
            'police_check_path' => 'acme/police.jpg',
            'fit_to_work_path' => 'acme/fit.png',
            'licences_json' => [[
                'id' => 7,
                'documentType' => 'Forklift',
                'storage_path' => 'acme/forklift.jpg',
            ]],
            'insurances_json' => [[
                'id' => 'pl',
                'type' => 'Public liability',
                'storage_path' => 'acme/insurance.jpg',
            ]],
            'vehicle_insurance_path' => 'acme/vehicle.jpg',
            'resume_path' => 'acme/resume.pdf',
        ]);

        $document = EmployeeProfilePdf::document($withImages, 'BluGreen');
        $sections = collect($document['sections']);

        $eligibility = $sections->firstWhere('title', 'WORK ELIGIBILITY & AVAILABILITY');
        $this->assertSame(['Visa document'], array_column($eligibility['images'], 'label'));

        $checks = $sections->firstWhere('title', 'ID & CHECKS');
        $this->assertSame(
            ['Passport', "Driver's Licence — back", 'Police check', 'Fit to work'],
            array_column($checks['images'], 'label'),
        );
        $this->assertStringStartsWith('data:image/jpeg;base64,', $checks['images'][0]['src']);
        $this->assertStringStartsWith('data:image/png;base64,', $checks['images'][1]['src']);

        $licences = $sections->firstWhere('title', 'LICENCES, INSURANCE & TRANSPORT');
        $this->assertSame(
            ['Forklift', 'Public liability', 'Vehicle insurance'],
            array_column($licences['images'], 'label'),
        );

        $pdfOnly = new Employee([
            'full_legal_name' => 'Alex Morgan',
            'fit_to_work_path' => 'acme/fit.pdf',
            'fit_to_work_uploaded' => 'Yes',
        ]);
        $pdfChecks = collect(EmployeeProfilePdf::document($pdfOnly, 'BluGreen')['sections'])
            ->firstWhere('title', 'ID & CHECKS');
        $this->assertSame([], $pdfChecks['images'] ?? []);
        $this->assertSame('On file', collect($pdfChecks['rows'])->firstWhere('label', 'Fit to work')['value']);
    }

    public function test_render_produces_a_pdf(): void
    {
        $employee = new Employee([
            'public_id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'full_legal_name' => 'Alex Morgan',
            'email' => 'alex@example.com',
            'employment_status' => 'active',
        ]);

        $pdf = EmployeeProfilePdf::render($employee, 'BluGreen');

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(2000, strlen($pdf));
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(12, 12);
        $blue = imagecolorallocate($image, 0, 61, 122);
        imagefilledrectangle($image, 0, 0, 11, 11, $blue);
        ob_start();
        imagepng($image);
        $binary = ob_get_clean();
        imagedestroy($image);

        return is_string($binary) ? $binary : '';
    }

    private function jpeg(): string
    {
        $image = imagecreatetruecolor(12, 12);
        $green = imagecolorallocate($image, 122, 193, 67);
        imagefilledrectangle($image, 0, 0, 11, 11, $green);
        ob_start();
        imagejpeg($image, null, 82);
        $binary = ob_get_clean();
        imagedestroy($image);

        return is_string($binary) ? $binary : '';
    }
}
