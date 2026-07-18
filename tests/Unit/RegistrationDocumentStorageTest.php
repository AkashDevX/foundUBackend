<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\RegistrationDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

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
}
