<?php

namespace Tests\Unit;

use App\Mail\EmployeeApplicationStatusMail;
use App\Models\Company;
use App\Models\Employee;
use App\Services\EmployeeApplicationMailer;
use App\Services\EmployeeRegistrationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class EmployeeApplicationStatusMailTest extends TestCase
{
    #[Test]
    public function received_approved_and_declined_emails_use_the_crulynk_layout(): void
    {
        config([
            'app.name' => 'CruLynk',
            'mail.from.address' => 'CruLynk@gmail.com',
            'mail.from.name' => 'CruLynk',
        ]);

        $employee = new Employee([
            'first_name' => 'Alex',
            'email' => 'alex@example.com',
            'public_id' => '11111111-1111-1111-1111-111111111111',
        ]);
        $company = new Company([
            'name' => 'Harbour Care',
            'slug' => 'harbour-care',
        ]);

        $expectations = [
            EmployeeApplicationStatusMail::RECEIVED => [
                'subject' => 'We have received your application — Harbour Care',
                'headline' => 'Application received',
                'label' => 'Under review',
                'body' => 'it is now under review',
            ],
            EmployeeApplicationStatusMail::APPROVED => [
                'subject' => 'Your application has been approved — Harbour Care',
                'headline' => 'Application approved',
                'label' => 'Approved',
                'body' => 'has approved your application',
            ],
            EmployeeApplicationStatusMail::DECLINED => [
                'subject' => 'An update on your application — Harbour Care',
                'headline' => 'Application update',
                'label' => 'Not approved',
                'body' => 'unable to approve your application',
            ],
        ];

        foreach ($expectations as $status => $expected) {
            $mailable = new EmployeeApplicationStatusMail($employee, $company, $status);
            $html = $mailable->render();

            $this->assertSame($expected['subject'], $mailable->envelope()->subject);
            $this->assertSame(config('mail.from.address'), $mailable->envelope()->from->address);
            $this->assertSame(config('mail.from.name'), $mailable->envelope()->from->name);
            $this->assertStringContainsString($expected['headline'], $html);
            $this->assertStringContainsString($expected['label'], $html);
            $this->assertStringContainsString($expected['body'], $html);
            $this->assertStringContainsString('Hello Alex,', $html);
            $this->assertStringContainsString('#003D7A', $html);
            $this->assertStringContainsString('Sent by CruLynk for Harbour Care.', $html);
        }
    }

    #[Test]
    public function submitting_an_application_emails_the_applicant_once_per_organisation(): void
    {
        Mail::fake();
        $this->bootTenant('tenant_mail_a');

        $company = new Company([
            'name' => 'Harbour Care',
            'slug' => 'harbour-care',
            'app_key' => 'key-a',
            'tenant_connection' => 'tenant_mail_a',
            'database_name' => 'tenant_mail_a_db',
            'is_active' => true,
            'is_platform_controller' => false,
        ]);

        $service = app(EmployeeRegistrationService::class);
        $payload = $service->normalizePayload([
            'email' => 'Alex.Applicant@Example.com',
            'password' => 'Password123!',
            'phone' => '0400000000',
        ]);

        $created = $service->createPendingEmployee(
            $company,
            Request::create('/api/v1/register-applications', 'POST', $payload),
            $payload,
            'Alex Applicant',
        );
        $duplicate = $service->createPendingEmployee(
            $company,
            Request::create('/api/v1/register-applications', 'POST', $payload),
            $payload,
            'Alex Applicant',
        );

        $this->assertSame(EmployeeRegistrationService::STATUS_CREATED, $created['status']);
        $this->assertSame(EmployeeRegistrationService::STATUS_ALREADY_APPLIED, $duplicate['status']);

        Mail::assertSent(EmployeeApplicationStatusMail::class, 1);
        Mail::assertSent(EmployeeApplicationStatusMail::class, function (EmployeeApplicationStatusMail $mail): bool {
            return $mail->status === EmployeeApplicationStatusMail::RECEIVED
                && $mail->hasTo('alex.applicant@example.com');
        });
    }

    #[Test]
    public function a_mail_outage_does_not_fail_the_application(): void
    {
        Log::spy();
        $this->bootTenant('tenant_mail_b');

        Mail::shouldReceive('to')
            ->once()
            ->andReturnUsing(function () {
                return new class
                {
                    public function send(mixed $mailable): void
                    {
                        throw new RuntimeException('smtp down');
                    }
                };
            });

        $company = new Company([
            'name' => 'Harbour Care',
            'slug' => 'harbour-care',
            'app_key' => 'key-b',
            'tenant_connection' => 'tenant_mail_b',
            'database_name' => 'tenant_mail_b_db',
            'is_active' => true,
            'is_platform_controller' => false,
        ]);

        $service = app(EmployeeRegistrationService::class);
        $payload = $service->normalizePayload([
            'email' => 'still.saved@example.com',
            'password' => 'Password123!',
        ]);

        $result = $service->createPendingEmployee(
            $company,
            Request::create('/api/v1/register-applications', 'POST', $payload),
            $payload,
            'Still Saved',
        );

        $this->assertSame(EmployeeRegistrationService::STATUS_CREATED, $result['status']);
        $this->assertSame('pending', Employee::on('tenant_mail_b')->where('email', 'still.saved@example.com')->value('employment_status'));
        Log::shouldHaveReceived('warning')->once();
    }

    #[Test]
    public function approval_and_decline_mailers_address_the_employee(): void
    {
        Mail::fake();

        $employee = new Employee([
            'first_name' => 'Alex',
            'email' => 'alex@example.com',
            'public_id' => '22222222-2222-2222-2222-222222222222',
        ]);
        $company = new Company([
            'name' => 'Harbour Care',
            'slug' => 'harbour-care',
        ]);

        $mailer = app(EmployeeApplicationMailer::class);
        $mailer->sendApproved($employee, $company);
        $mailer->sendDeclined($employee, $company);

        Mail::assertSent(EmployeeApplicationStatusMail::class, function (EmployeeApplicationStatusMail $mail): bool {
            return $mail->status === EmployeeApplicationStatusMail::APPROVED && $mail->hasTo('alex@example.com');
        });
        Mail::assertSent(EmployeeApplicationStatusMail::class, function (EmployeeApplicationStatusMail $mail): bool {
            return $mail->status === EmployeeApplicationStatusMail::DECLINED && $mail->hasTo('alex@example.com');
        });
    }

    private function bootTenant(string $connection): void
    {
        config()->set("database.connections.{$connection}", [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        DB::purge($connection);
        DB::reconnect($connection);

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
