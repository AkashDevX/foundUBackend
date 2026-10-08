<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Services\EmployeeRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmployeeRegistrationServiceTest extends TestCase
{
    private EmployeeRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EmployeeRegistrationService::class);

        foreach (['tenant_test_a', 'tenant_test_b'] as $connection) {
            config()->set("database.connections.{$connection}", [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]);
            DB::purge($connection);
            DB::reconnect($connection);
            $this->createEmployeesTable($connection);
        }
    }

    #[Test]
    public function normalize_payload_clears_vehicle_fields_when_not_own_vehicle(): void
    {
        $payload = $this->service->normalizePayload([
            'mode_of_transport' => 'Public transport',
            'vehicle_registration' => 'ABC123',
            'vehicle_expiry' => '2030-01-01',
            'vehicle_insurance_uploaded' => 'Yes',
        ]);

        $this->assertNull($payload['vehicle_registration']);
        $this->assertNull($payload['vehicle_expiry']);
        $this->assertNull($payload['vehicle_insurance_uploaded']);
    }

    #[Test]
    public function normalize_payload_clears_visa_expiry_when_work_rights_are_unrestricted(): void
    {
        $cleared = $this->service->normalizePayload([
            'unrestricted_work_rights' => 'Yes',
            'visa_expiry' => '2030-04-01',
            'visa_status' => 'Temporary Visa - Working',
        ]);
        $this->assertNull($cleared['visa_expiry']);

        $kept = $this->service->normalizePayload([
            'unrestricted_work_rights' => 'No',
            'visa_expiry' => '2030-04-01',
            'visa_status' => 'Temporary Visa - Student',
        ]);
        $this->assertSame('2030-04-01', $kept['visa_expiry']);
    }

    #[Test]
    public function classify_existing_status_maps_pending_and_active(): void
    {
        $this->assertSame(
            EmployeeRegistrationService::STATUS_ALREADY_APPLIED,
            $this->service->classifyExistingEmploymentStatus('pending'),
        );
        $this->assertSame(
            EmployeeRegistrationService::STATUS_ALREADY_REGISTERED,
            $this->service->classifyExistingEmploymentStatus('active'),
        );
        $this->assertNull($this->service->classifyExistingEmploymentStatus(null));
    }

    #[Test]
    public function app_key_must_match_when_company_has_a_key(): void
    {
        $company = new Company([
            'app_key' => 'secret-key',
            'is_platform_controller' => false,
            'tenant_connection' => 'tenant_test_a',
        ]);

        $this->assertTrue($this->service->appKeyIsValid($company, 'secret-key'));
        $this->assertFalse($this->service->appKeyIsValid($company, 'wrong'));
        $this->assertTrue($this->service->appKeyIsValid(
            new Company(['app_key' => null, 'is_platform_controller' => false, 'tenant_connection' => 'tenant_test_a']),
            null,
        ));
    }

    #[Test]
    public function fan_out_creates_pending_employees_in_each_tenant(): void
    {
        $alpha = $this->makeCompany('alpha', 'Alpha Co', 'tenant_test_a', 'key-a');
        $beta = $this->makeCompany('beta', 'Beta Co', 'tenant_test_b', 'key-b');
        $payload = $this->basePayload('join.both@example.com');
        $request = Request::create('/api/v1/register-applications', 'POST', $payload);

        $alphaResult = $this->service->createPendingEmployee($alpha, $request, $payload, 'Alex Rivera');
        $betaResult = $this->service->createPendingEmployee($beta, $request, $payload, 'Alex Rivera');

        $this->assertSame(EmployeeRegistrationService::STATUS_CREATED, $alphaResult['status']);
        $this->assertSame(EmployeeRegistrationService::STATUS_CREATED, $betaResult['status']);

        $this->assertSame(1, Employee::on('tenant_test_a')->where('email', 'join.both@example.com')->count());
        $this->assertSame(1, Employee::on('tenant_test_b')->where('email', 'join.both@example.com')->count());
        $this->assertSame('pending', Employee::on('tenant_test_a')->where('email', 'join.both@example.com')->value('employment_status'));
        $this->assertSame('pending', Employee::on('tenant_test_b')->where('email', 'join.both@example.com')->value('employment_status'));
        $this->assertSame('alpha', Employee::on('tenant_test_a')->where('email', 'join.both@example.com')->value('registration_company_slug'));
        $this->assertSame('beta', Employee::on('tenant_test_b')->where('email', 'join.both@example.com')->value('registration_company_slug'));
    }

    #[Test]
    public function duplicate_email_in_one_tenant_does_not_block_the_other(): void
    {
        $alpha = $this->makeCompany('alpha', 'Alpha Co', 'tenant_test_a', 'key-a');
        $beta = $this->makeCompany('beta', 'Beta Co', 'tenant_test_b', 'key-b');
        $payload = $this->basePayload('already.there@example.com');

        Employee::on('tenant_test_a')->create([
            'first_name' => 'Existing',
            'last_name' => 'User',
            'full_legal_name' => 'Existing User',
            'email' => 'already.there@example.com',
            'password' => 'Password123!',
            'employment_status' => 'pending',
        ]);

        $request = Request::create('/api/v1/register-applications', 'POST', $payload);
        $alphaResult = $this->service->createPendingEmployee($alpha, $request, $payload, 'Alex Rivera');
        $betaResult = $this->service->createPendingEmployee($beta, $request, $payload, 'Alex Rivera');

        $this->assertSame(EmployeeRegistrationService::STATUS_ALREADY_APPLIED, $alphaResult['status']);
        $this->assertSame(EmployeeRegistrationService::STATUS_CREATED, $betaResult['status']);
        $this->assertSame(1, Employee::on('tenant_test_a')->where('email', 'already.there@example.com')->count());
        $this->assertSame(1, Employee::on('tenant_test_b')->where('email', 'already.there@example.com')->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function basePayload(string $email): array
    {
        return $this->service->normalizePayload([
            'email' => $email,
            'password' => 'Password123!',
            'phone' => '0400000000',
            'mode_of_transport' => 'Public transport',
        ]);
    }

    private function makeCompany(string $slug, string $name, string $connection, string $appKey): Company
    {
        return new Company([
            'name' => $name,
            'slug' => $slug,
            'app_key' => $appKey,
            'tenant_connection' => $connection,
            'database_name' => $connection.'_db',
            'is_active' => true,
            'is_platform_controller' => false,
        ]);
    }

    private function createEmployeesTable(string $connection): void
    {
        Schema::connection($connection)->create('employees', function ($table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->string('full_legal_name', 200)->nullable();
            $table->string('email');
            $table->string('phone', 48)->nullable();
            $table->string('password');
            $table->string('registration_company_slug', 120)->nullable();
            $table->string('registration_company_app_key', 64)->nullable();
            $table->string('company_display_name', 200)->nullable();
            $table->string('employment_status', 32)->default('pending');
            $table->string('mode_of_transport', 64)->nullable();
            $table->string('vehicle_registration', 64)->nullable();
            $table->string('vehicle_expiry', 32)->nullable();
            $table->string('vehicle_insurance_uploaded', 8)->nullable();
            $table->string('vehicle_insurance_path', 512)->nullable();
            $table->string('profile_photo_path', 512)->nullable();
            $table->string('police_check_path', 512)->nullable();
            $table->string('fit_to_work_path', 512)->nullable();
            $table->text('bank_account_number')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['email']);
        });
    }
}
