<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\RegistrationDocumentStorage;
use App\Support\RegistrationDisplay;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Tests\TestCase;

#[AllowMockObjectsWithoutExpectations]
class RegistrationDocumentStorageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('employee_registration');
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

    public function test_remove_then_upload_replaces_scalar_profile_photo_path(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-001',
            'profile_photo_path' => 'acme/emp-001/old-photo.jpg',
        ]);
        Storage::disk('employee_registration')->put('acme/emp-001/old-photo.jpg', 'old-image');

        $removeRequest = Request::create('/', 'POST', [
            'remove_profile_photo' => '1',
        ]);
        app(RegistrationDocumentStorage::class)->attach($removeRequest, $employee, 'acme');

        $this->assertNull($employee->profile_photo_path);
        Storage::disk('employee_registration')->assertMissing('acme/emp-001/old-photo.jpg');

        $uploadRequest = Request::create('/', 'POST', [], [], [
            'profile_photo' => UploadedFile::fake()->image('new-photo.jpg'),
        ]);
        app(RegistrationDocumentStorage::class)->attach($uploadRequest, $employee, 'acme');

        $this->assertNotNull($employee->profile_photo_path);
        $this->assertNotSame('acme/emp-001/old-photo.jpg', $employee->profile_photo_path);
        Storage::disk('employee_registration')->assertExists($employee->profile_photo_path);
    }

    public function test_remove_then_upload_replaces_json_document_path(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-002',
            'licences_json' => [
                [
                    'id' => 7,
                    'documentType' => 'Forklift',
                    'storage_path' => 'acme/emp-002/old-licence.jpg',
                    'uri' => 'file:///old-local.jpg',
                ],
            ],
        ]);
        Storage::disk('employee_registration')->put('acme/emp-002/old-licence.jpg', 'old-licence');

        $removeRequest = Request::create('/', 'POST', [
            'remove_licence_upload' => ['7' => '1'],
        ]);
        app(RegistrationDocumentStorage::class)->attach($removeRequest, $employee, 'acme');

        $this->assertArrayNotHasKey('storage_path', $employee->licences_json[0]);
        $this->assertArrayNotHasKey('uri', $employee->licences_json[0]);
        Storage::disk('employee_registration')->assertMissing('acme/emp-002/old-licence.jpg');

        $uploadRequest = Request::create('/', 'POST', [], [], [
            'licence_upload' => [
                '7' => UploadedFile::fake()->image('new-licence.jpg'),
            ],
        ]);
        app(RegistrationDocumentStorage::class)->attach($uploadRequest, $employee, 'acme');

        $newPath = $employee->licences_json[0]['storage_path'] ?? null;
        $this->assertIsString($newPath);
        $this->assertNotSame('acme/emp-002/old-licence.jpg', $newPath);
        Storage::disk('employee_registration')->assertExists($newPath);
    }

    public function test_upload_skips_remove_flag_for_same_slot(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-003',
            'police_check_path' => 'acme/emp-003/old-check.pdf',
        ]);
        Storage::disk('employee_registration')->put('acme/emp-003/old-check.pdf', 'old-check');

        $request = Request::create('/', 'POST', [
            'remove_police_check' => '1',
        ], [], [
            'police_check' => UploadedFile::fake()->create('new-check.pdf', 8, 'application/pdf'),
        ]);

        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $this->assertNotNull($employee->police_check_path);
        $this->assertNotSame('acme/emp-003/old-check.pdf', $employee->police_check_path);
        Storage::disk('employee_registration')->assertExists($employee->police_check_path);
    }

    public function test_temporary_visa_pdf_and_image_are_stored(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-visa',
            'visa_status' => 'Working Holiday Visa',
        ]);

        $request = Request::create('/', 'POST', [], [], [
            'visa_document' => UploadedFile::fake()->create('grant.pdf', 20, 'application/pdf'),
        ]);
        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $this->assertIsString($employee->visa_document_path);
        $this->assertStringEndsWith('.pdf', $employee->visa_document_path);
        Storage::disk('employee_registration')->assertExists($employee->visa_document_path);

        $replace = Request::create('/', 'POST', [], [], [
            'visa_document' => UploadedFile::fake()->image('visa-label.jpg'),
        ]);
        app(RegistrationDocumentStorage::class)->attach($replace, $employee, 'acme');

        $this->assertIsString($employee->visa_document_path);
        $this->assertStringEndsWith('.jpg', $employee->visa_document_path);
        Storage::disk('employee_registration')->assertExists($employee->visa_document_path);
    }

    public function test_non_temporary_visa_status_drops_a_stored_visa_document(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-citizen',
            'visa_status' => 'Australian Citizen',
            'visa_document_path' => 'acme/emp-citizen/old-visa.png',
        ]);
        Storage::disk('employee_registration')->put('acme/emp-citizen/old-visa.png', 'old-visa');

        $request = Request::create('/', 'POST', [], [], [
            'visa_document' => UploadedFile::fake()->create('grant.pdf', 8, 'application/pdf'),
        ]);
        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $this->assertNull($employee->visa_document_path);
        Storage::disk('employee_registration')->assertMissing('acme/emp-citizen/old-visa.png');
    }

    public function test_resume_stores_pdf_doc_and_docx_and_ignores_other_types(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-resume',
        ]);

        foreach ([
            ['cv.pdf', 'application/pdf', '.pdf'],
            ['cv.doc', 'application/msword', '.doc'],
            ['cv.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', '.docx'],
        ] as [$name, $mime, $extension]) {
            $request = Request::create('/', 'POST', [], [], [
                'resume' => UploadedFile::fake()->create($name, 12, $mime),
            ]);
            app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

            $this->assertIsString($employee->resume_path);
            $this->assertStringEndsWith($extension, $employee->resume_path);
            Storage::disk('employee_registration')->assertExists($employee->resume_path);
        }

        $kept = $employee->resume_path;
        $rejected = Request::create('/', 'POST', [], [], [
            'resume' => UploadedFile::fake()->create('photo.jpg', 8, 'image/jpeg'),
        ]);
        app(RegistrationDocumentStorage::class)->attach($rejected, $employee, 'acme');

        $this->assertSame($kept, $employee->resume_path);
        Storage::disk('employee_registration')->assertExists($kept);
    }

    public function test_resume_can_be_removed(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-resume-remove',
            'resume_path' => 'acme/emp-resume-remove/old-cv.pdf',
        ]);
        Storage::disk('employee_registration')->put('acme/emp-resume-remove/old-cv.pdf', 'old-cv');

        $request = Request::create('/', 'POST', [
            'remove_resume' => '1',
        ]);
        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $this->assertNull($employee->resume_path);
        Storage::disk('employee_registration')->assertMissing('acme/emp-resume-remove/old-cv.pdf');
    }

    public function test_drivers_licence_stores_front_image_and_back_pdf(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-id-1',
            'id_documents_json' => [[
                'documentKey' => '1',
                'idType' => "Driver's Licence",
                'imageUploaded' => true,
                'backImageUploaded' => true,
            ]],
        ]);

        $request = Request::create('/', 'POST', [], [], [
            'id_document_upload' => [
                '1' => UploadedFile::fake()->image('front.jpg'),
            ],
            'id_document_back_upload' => [
                '1' => UploadedFile::fake()->create('back.pdf', 20, 'application/pdf'),
            ],
        ]);
        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $front = $employee->id_documents_json[0]['storage_path'] ?? null;
        $back = $employee->id_documents_json[0]['back_storage_path'] ?? null;
        $this->assertIsString($front);
        $this->assertIsString($back);
        $this->assertNotSame($front, $back);
        Storage::disk('employee_registration')->assertExists($front);
        Storage::disk('employee_registration')->assertExists($back);
        $this->assertSame($front, RegistrationDisplay::registrationStoragePath($employee, 'id-document', '1'));
        $this->assertSame($back, RegistrationDisplay::registrationStoragePath($employee, 'id-document-back', '1'));
    }

    public function test_back_upload_is_ignored_unless_the_id_is_a_drivers_licence(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-id-2',
            'id_documents_json' => [[
                'documentKey' => '2',
                'idType' => 'Passport',
                'imageUploaded' => true,
            ]],
        ]);

        $request = Request::create('/', 'POST', [], [], [
            'id_document_upload' => [
                '2' => UploadedFile::fake()->create('passport.pdf', 20, 'application/pdf'),
            ],
            'id_document_back_upload' => [
                '2' => UploadedFile::fake()->image('back.jpg'),
            ],
        ]);
        app(RegistrationDocumentStorage::class)->attach($request, $employee, 'acme');

        $this->assertIsString($employee->id_documents_json[0]['storage_path'] ?? null);
        $this->assertArrayNotHasKey('back_storage_path', $employee->id_documents_json[0]);
    }

    public function test_changing_away_from_drivers_licence_removes_the_back_file(): void
    {
        $employee = $this->makeEmployee([
            'public_id' => 'emp-id-3',
            'id_documents_json' => [[
                'documentKey' => '3',
                'documentType' => 'Passport',
                'idType' => "Driver's Licence",
                'storage_path' => 'acme/emp-id-3/front.jpg',
                'back_storage_path' => 'acme/emp-id-3/back.jpg',
            ]],
        ]);
        Storage::disk('employee_registration')->put('acme/emp-id-3/front.jpg', 'front');
        Storage::disk('employee_registration')->put('acme/emp-id-3/back.jpg', 'back');

        app(RegistrationDocumentStorage::class)->attach(Request::create('/', 'POST'), $employee, 'acme');

        $this->assertSame('acme/emp-id-3/front.jpg', $employee->id_documents_json[0]['storage_path']);
        $this->assertArrayNotHasKey('back_storage_path', $employee->id_documents_json[0]);
        Storage::disk('employee_registration')->assertMissing('acme/emp-id-3/back.jpg');
        Storage::disk('employee_registration')->assertExists('acme/emp-id-3/front.jpg');
    }
}
