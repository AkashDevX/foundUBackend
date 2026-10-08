<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Employee;
use App\Support\FoundUProfileMapper;
use App\Support\RegistrationDisplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Creates pending tenant employees from the foundU Create Account wizard.
 */
class EmployeeRegistrationService
{
    public const STATUS_CREATED = 'created';

    public const STATUS_ALREADY_APPLIED = 'already_applied';

    public const STATUS_ALREADY_REGISTERED = 'already_registered';

    public const STATUS_FAILED = 'failed';

    public const MAX_COMPANIES = 20;

    /** @var list<string> */
    public const PAYLOAD_KEYS = [
        'registration_company_slug',
        'registration_company_app_key',
        'company_display_name',
        'email',
        'password',
        'phone',
        'date_of_birth',
        'sex',
        'marital_status',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relationship',
        'visa_status',
        'unrestricted_work_rights',
        'visa_expiry',
        'hours_per_week',
        'weekly_availability_summary',
        'weekly_availability_json',
        'id_documents_summary',
        'id_documents_json',
        'police_check_expiry',
        'police_check_uploaded',
        'fit_to_work_expiry',
        'fit_to_work_uploaded',
        'licences_summary',
        'insurances_summary',
        'licences_json',
        'insurances_json',
        'bank_account_name',
        'bank_account_number',
        'bank_branch_code',
        'bank_name',
        'mode_of_transport',
        'vehicle_registration',
        'vehicle_expiry',
        'vehicle_insurance_uploaded',
        'employee_code',
        'job_title',
        'department',
    ];

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function payloadFromValidated(array $validated): array
    {
        $payload = [];
        foreach (self::PAYLOAD_KEYS as $key) {
            if (array_key_exists($key, $validated)) {
                $payload[$key] = $validated[$key];
            }
        }

        return $this->normalizePayload($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function normalizePayload(array $payload): array
    {
        foreach (['date_of_birth', 'visa_expiry', 'police_check_expiry', 'fit_to_work_expiry', 'vehicle_expiry'] as $dateField) {
            if (array_key_exists($dateField, $payload)) {
                $payload[$dateField] = RegistrationDisplay::toNullableIsoDate($payload[$dateField]);
            }
        }

        if (array_key_exists('licences_json', $payload)) {
            $payload['licences_json'] = RegistrationDisplay::normalizeDocumentJsonExpiryRows(
                is_array($payload['licences_json']) ? $payload['licences_json'] : null
            );
            $payload['licences_summary'] = RegistrationDisplay::rebuildDocumentRowsSummary(
                is_array($payload['licences_json']) ? $payload['licences_json'] : null
            ) ?? ($payload['licences_summary'] ?? null);
        }

        if (array_key_exists('insurances_json', $payload)) {
            $payload['insurances_json'] = RegistrationDisplay::normalizeDocumentJsonExpiryRows(
                is_array($payload['insurances_json']) ? $payload['insurances_json'] : null
            );
            $payload['insurances_summary'] = RegistrationDisplay::rebuildDocumentRowsSummary(
                is_array($payload['insurances_json']) ? $payload['insurances_json'] : null
            ) ?? ($payload['insurances_summary'] ?? null);
        }

        if (! $this->transportIsOwnVehicle($payload['mode_of_transport'] ?? null)) {
            $payload['vehicle_registration'] = null;
            $payload['vehicle_expiry'] = null;
            $payload['vehicle_insurance_uploaded'] = null;
        }

        if (array_key_exists('unrestricted_work_rights', $payload)
            && $this->unrestrictedWorkRightsIsYes($payload['unrestricted_work_rights'] ?? null)) {
            $payload['visa_expiry'] = null;
        }

        return $payload;
    }

    public function unrestrictedWorkRightsIsYes(mixed $value): bool
    {
        if (! is_string($value) || trim($value) === '') {
            return false;
        }

        return strcasecmp(trim($value), 'Yes') === 0;
    }

    public function transportIsOwnVehicle(?string $mode): bool
    {
        if ($mode === null || trim($mode) === '') {
            return false;
        }

        return strcasecmp(trim($mode), 'Own vehicle') === 0;
    }

    public function appKeyIsValid(Company $company, ?string $submittedKey): bool
    {
        $registryAppKey = $company->app_key;
        if ($registryAppKey === null || $registryAppKey === '') {
            return true;
        }

        return $submittedKey === $registryAppKey;
    }

    /**
     * @return self::STATUS_ALREADY_APPLIED|self::STATUS_ALREADY_REGISTERED|null
     */
    public function classifyExistingEmploymentStatus(?string $employmentStatus): ?string
    {
        if ($employmentStatus === null || trim($employmentStatus) === '') {
            return null;
        }

        $status = strtolower(trim($employmentStatus));

        if (in_array($status, ['pending', 'declined', 'rejected'], true)) {
            return self::STATUS_ALREADY_APPLIED;
        }

        return self::STATUS_ALREADY_REGISTERED;
    }

    /**
     * @return array{ok: true, company: Company}|array{ok: false, result: array<string, mixed>}
     */
    public function resolveRegistrableCompany(string $slug, ?string $appKey): array
    {
        $slug = trim($slug);

        /** @var Company|null $company */
        $company = Company::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($company === null || ! $company->hasTenantDatabase()) {
            return [
                'ok' => false,
                'result' => [
                    'slug' => $slug,
                    'name' => $slug,
                    'status' => self::STATUS_FAILED,
                    'message' => 'Unknown or inactive organisation.',
                ],
            ];
        }

        if (! $this->appKeyIsValid($company, $appKey)) {
            return [
                'ok' => false,
                'result' => [
                    'slug' => $company->slug,
                    'name' => $company->name,
                    'status' => self::STATUS_FAILED,
                    'message' => 'Organization credentials do not match the master registry. Use GET /api/v1/bootstrap and send appKey equal to the company appKey.',
                ],
            ];
        }

        return ['ok' => true, 'company' => $company];
    }

    /**
     * @param  list<array{slug?: string, app_key?: string|null}>  $companyRows
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public function registerApplications(Request $request, array $companyRows, array $payload, string $fullLegalName): array
    {
        $results = [];
        $seen = [];

        foreach ($companyRows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '' || isset($seen[$slug])) {
                continue;
            }
            $seen[$slug] = true;

            $resolved = $this->resolveRegistrableCompany($slug, $row['app_key'] ?? null);
            if (! $resolved['ok']) {
                $results[] = $resolved['result'];

                continue;
            }

            $results[] = $this->createPendingEmployee($resolved['company'], $request, $payload, $fullLegalName);
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createPendingEmployee(Company $company, Request $request, array $payload, string $fullLegalName): array
    {
        $connection = $company->tenant_connection;
        if (! is_string($connection) || $connection === '' || ! $company->hasTenantDatabase()) {
            return [
                'slug' => $company->slug,
                'name' => $company->name,
                'status' => self::STATUS_FAILED,
                'message' => 'This organisation has no tenant database.',
            ];
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        [$firstName, $lastName] = FoundUProfileMapper::splitFullLegalName($fullLegalName);

        $tenantPayload = $payload;
        $tenantPayload['registration_company_slug'] = $company->slug;
        $tenantPayload['registration_company_app_key'] = $company->app_key;
        $tenantPayload['company_display_name'] = $company->name;
        $tenantPayload['email'] = $email;

        $previous = DB::getDefaultConnection();

        try {
            DB::setDefaultConnection($connection);

            return DB::connection($connection)->transaction(function () use (
                $company,
                $request,
                $tenantPayload,
                $firstName,
                $lastName,
                $fullLegalName,
                $email,
            ): array {
                $existing = Employee::query()
                    ->withTrashed()
                    ->where('email', $email)
                    ->first();

                if ($existing instanceof Employee) {
                    $conflict = $this->classifyExistingEmploymentStatus($existing->employment_status) ?? self::STATUS_ALREADY_REGISTERED;

                    return [
                        'slug' => $company->slug,
                        'name' => $company->name,
                        'status' => $conflict,
                        'message' => $conflict === self::STATUS_ALREADY_APPLIED
                            ? 'An application with this email is already awaiting review at this organisation.'
                            : 'An account with this email already exists at this organisation.',
                        'public_id' => $existing->public_id,
                    ];
                }

                $employee = Employee::query()->create([
                    ...$tenantPayload,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'full_legal_name' => $fullLegalName,
                    'employment_status' => 'pending',
                ]);

                app(RegistrationDocumentStorage::class)->attach($request, $employee, $company->slug);

                $uploadFlags = [];
                if ($request->hasFile('police_check')) {
                    $uploadFlags['police_check_uploaded'] = 'Yes';
                }
                if ($request->hasFile('fit_to_work')) {
                    $uploadFlags['fit_to_work_uploaded'] = 'Yes';
                }
                if ($request->hasFile('vehicle_insurance')) {
                    $uploadFlags['vehicle_insurance_uploaded'] = 'Yes';
                }
                if ($uploadFlags !== []) {
                    $employee->forceFill($uploadFlags)->save();
                }

                if (! $this->transportIsOwnVehicle($employee->mode_of_transport)) {
                    $employee->forceFill([
                        'vehicle_registration' => null,
                        'vehicle_expiry' => null,
                        'vehicle_insurance_uploaded' => null,
                        'vehicle_insurance_path' => null,
                    ])->save();
                }

                return [
                    'slug' => $company->slug,
                    'name' => $company->name,
                    'status' => self::STATUS_CREATED,
                    'message' => 'Application received.',
                    'public_id' => $employee->public_id,
                    'employee' => $employee,
                ];
            });
        } catch (Throwable $e) {
            report($e);

            return [
                'slug' => $company->slug,
                'name' => $company->name,
                'status' => self::STATUS_FAILED,
                'message' => 'Could not submit application to this organisation.',
            ];
        } finally {
            DB::setDefaultConnection($previous);
        }
    }
}
