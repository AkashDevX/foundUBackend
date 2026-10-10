<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Support\DocumentRenewal;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class DocumentRenewalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('employee_registration');
        Carbon::setTestNow(Carbon::parse('2026-10-10 02:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeEmployee(array $attributes): Employee
    {
        $employee = $this->getMockBuilder(Employee::class)
            ->onlyMethods(['save'])
            ->getMock();
        $employee->method('save')->willReturn(true);
        $employee->forceFill($attributes);

        return $employee;
    }

    public function test_documents_inside_thirty_days_or_already_expired_are_due(): void
    {
        $employee = $this->makeEmployee([
            'visa_status' => 'Temporary visa',
            'visa_expiry' => '2026-11-09',
            'police_check_expiry' => '2026-10-01',
            'fit_to_work_expiry' => '2026-11-10',
            'vehicle_expiry' => '2026-10-10',
            'licences_json' => [
                ['id' => 7, 'documentType' => 'Forklift', 'expiry' => '2026-10-20'],
                ['id' => 8, 'documentType' => 'HR', 'expiry' => '2027-01-01'],
            ],
            'insurances_json' => [
                ['id' => 'pl', 'type' => 'Public liability', 'expiry_date' => '2026-10-25'],
            ],
        ]);

        $keys = array_column(DocumentRenewal::dueItems($employee), 'key');

        $this->assertSame(
            ['visa', 'police_check', 'vehicle_registration', 'licence:7', 'insurance:pl'],
            $keys,
        );

        $police = DocumentRenewal::dueItems($employee)[1];
        $this->assertSame('expired', $police['status']);
        $this->assertSame(-9, $police['days_until']);

        $vehicle = DocumentRenewal::dueItems($employee)[2];
        $this->assertSame(0, $vehicle['days_until']);
        $this->assertSame('expiring', $vehicle['status']);
    }

    public function test_citizen_visa_expiry_is_ignored(): void
    {
        $employee = $this->makeEmployee([
            'visa_status' => 'Australian citizen',
            'visa_expiry' => '2026-10-20',
        ]);

        $this->assertSame([], DocumentRenewal::dueItems($employee));
    }

    public function test_reminder_copy_names_the_document_and_says_it_repeats_daily(): void
    {
        $one = DocumentRenewal::reminderCopy([
            [
                'key' => 'police_check',
                'label' => 'Police check',
                'expiry' => '2026-10-20',
                'days_until' => 10,
                'status' => 'expiring',
            ],
        ]);

        $this->assertSame('Your Police check expires in 10 days', $one['title']);
        $this->assertStringContainsString('Your Police check expires in 10 days. Please renew it.', $one['body']);
        $this->assertStringContainsString('until you submit it', $one['body']);

        $many = DocumentRenewal::reminderCopy([
            ['key' => 'a', 'label' => 'Police check', 'expiry' => '2026-10-20', 'days_until' => 10, 'status' => 'expiring'],
            ['key' => 'b', 'label' => 'Forklift', 'expiry' => '2026-10-21', 'days_until' => 11, 'status' => 'expiring'],
            ['key' => 'c', 'label' => 'Visa', 'expiry' => '2026-10-22', 'days_until' => 12, 'status' => 'expiring'],
            ['key' => 'd', 'label' => 'White card', 'expiry' => '2026-10-23', 'days_until' => 13, 'status' => 'expiring'],
        ]);

        $this->assertSame('Documents need renewing', $many['title']);
        $this->assertStringContainsString('Your Police check expires in 10 days. Please renew it.', $many['body']);
        $this->assertStringContainsString('Your White card expires in 13 days. Please renew it.', $many['body']);
    }

    public function test_apply_replaces_police_check_and_stops_reminders_when_expiry_is_outside_the_window(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-001',
            'police_check_expiry' => '2026-10-20',
            'police_check_path' => 'acme/emp-001/old-check.pdf',
            'police_check_uploaded' => 'Yes',
        ]);
        Storage::disk('employee_registration')->put('acme/emp-001/old-check.pdf', 'old');

        $result = DocumentRenewal::apply(
            $employee,
            'acme',
            'police_check',
            '2026-12-01',
            UploadedFile::fake()->create('renewed.pdf', 20, 'application/pdf'),
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['still_due']);
        $this->assertSame('2026-12-01', $employee->police_check_expiry);
        $this->assertSame('Yes', $employee->police_check_uploaded);
        $this->assertNotSame('acme/emp-001/old-check.pdf', $employee->police_check_path);
        Storage::disk('employee_registration')->assertMissing('acme/emp-001/old-check.pdf');
        Storage::disk('employee_registration')->assertExists($employee->police_check_path);
        $this->assertSame([], DocumentRenewal::dueItems($employee));
    }

    public function test_apply_rejects_an_expiry_that_is_not_after_today(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-002',
            'fit_to_work_expiry' => '2026-10-12',
        ]);

        $result = DocumentRenewal::apply(
            $employee,
            'acme',
            'fit_to_work',
            '2026-10-10',
            UploadedFile::fake()->image('fit.jpg'),
        );

        $this->assertFalse($result['ok']);
        $this->assertSame('2026-10-12', $employee->fit_to_work_expiry);
    }

    public function test_apply_updates_a_licence_row_and_keeps_it_due_when_the_new_date_is_soon(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-003',
            'licences_json' => [
                [
                    'id' => 7,
                    'documentType' => 'Forklift',
                    'expiry' => '2026-10-20',
                    'storage_path' => 'acme/emp-003/old-licence.jpg',
                ],
            ],
        ]);
        Storage::disk('employee_registration')->put('acme/emp-003/old-licence.jpg', 'old');

        $result = DocumentRenewal::apply(
            $employee,
            'acme',
            'licence:7',
            '2026-11-01',
            UploadedFile::fake()->image('forklift.jpg'),
        );

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['still_due']);
        $rows = $employee->licences_json;
        $this->assertSame('2026-11-01', $rows[0]['expiry']);
        $this->assertSame('2026-11-01', $rows[0]['expiry_date']);
        $this->assertTrue($rows[0]['imageUploaded']);
        $this->assertNotSame('acme/emp-003/old-licence.jpg', $rows[0]['storage_path']);
        Storage::disk('employee_registration')->assertMissing('acme/emp-003/old-licence.jpg');
        $this->assertSame(['licence:7'], array_column(DocumentRenewal::dueItems($employee), 'key'));
        $this->assertStringContainsString('Forklift', (string) $employee->licences_summary);
    }

    public function test_apply_stores_a_renewed_vehicle_registration_document(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-004',
            'vehicle_expiry' => '2026-10-15',
            'profile_metadata' => ['vehicle_registration_path' => 'acme/emp-004/old-rego.pdf'],
        ]);
        Storage::disk('employee_registration')->put('acme/emp-004/old-rego.pdf', 'old');

        $result = DocumentRenewal::apply(
            $employee,
            'acme',
            'vehicle_registration',
            '2027-10-15',
            UploadedFile::fake()->create('rego.pdf', 20, 'application/pdf'),
        );

        $this->assertTrue($result['ok']);
        $this->assertFalse($result['still_due']);
        $this->assertSame('2027-10-15', $employee->vehicle_expiry);
        $path = DocumentRenewal::vehicleRegistrationPath($employee);
        $this->assertNotNull($path);
        $this->assertNotSame('acme/emp-004/old-rego.pdf', $path);
        Storage::disk('employee_registration')->assertMissing('acme/emp-004/old-rego.pdf');
        Storage::disk('employee_registration')->assertExists($path);
    }
}
