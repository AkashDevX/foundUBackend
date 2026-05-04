<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenantFromMaster;
use App\Http\Requests\RegisterEmployeeRequest;
use App\Models\Employee;
use App\Services\RegistrationDocumentStorage;
use App\Support\FoundUProfileMapper;
use App\Support\RegistrationDisplay;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Persists the full four-step foundU registration into the tenant `employees` table.
 * Tenant is resolved by X-Company-Slug via {@see ResolveTenantFromMaster}.
 */
class RegisterEmployeeController extends Controller
{
    public function __invoke(RegisterEmployeeRequest $request): JsonResponse
    {
        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        if ($request->validated('registration_company_slug') !== $company->slug) {
            return response()->json([
                'message' => 'Selected company does not match X-Company-Slug (tenant).',
            ], 422);
        }

        $registryAppKey = $company->app_key;
        if ($registryAppKey !== null && $registryAppKey !== '') {
            $submittedKey = $request->validated('registration_company_app_key');
            if ($submittedKey !== $registryAppKey) {
                return response()->json([
                    'message' => 'Organization credentials do not match the master registry. Use GET /api/v1/bootstrap and send registration_company_app_key equal to the company appKey.',
                    'code' => 'invalid_organization_key',
                ], 422);
            }
        }

        [$firstName, $lastName] = FoundUProfileMapper::splitFullLegalName($request->validated('full_legal_name'));

        $payload = $request->safe()->only([
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
        ]);

        foreach (['date_of_birth', 'visa_expiry', 'police_check_expiry', 'fit_to_work_expiry', 'vehicle_expiry'] as $dateField) {
            if (array_key_exists($dateField, $payload)) {
                $payload[$dateField] = RegistrationDisplay::toNullableIsoDate($payload[$dateField]);
            }
        }

        $employee = null;

        DB::connection()->transaction(function () use (
            &$employee,
            $payload,
            $firstName,
            $lastName,
            $request,
            $company,
        ): void {
            $employee = Employee::query()->create([
                ...$payload,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'full_legal_name' => $request->validated('full_legal_name'),
                'employment_status' => 'pending',
            ]);

            app(RegistrationDocumentStorage::class)->attach($request, $employee, $company->slug);
        });

        abort_unless($employee instanceof Employee, 500, 'Registration failed.');

        /*
         * Mobile clients: never treat this response as logged-in. There is no token/session.
         * After org approval the user must call POST /api/v1/login with email + password only.
         */
        return response()->json([
            'message' => 'Application received. Your organization will review it — you can sign in to the app only after they approve your registration.',
            'company' => [
                'slug' => $company->slug,
                'name' => $company->name,
            ],
            'employee' => [
                'public_id' => $employee->public_id,
                'full_legal_name' => $employee->full_legal_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'email' => $employee->email,
                'employment_status' => $employee->employment_status,
            ],
            'auth' => [
                'authenticated' => false,
                'token_issued' => false,
                'requires_email_password_login_after_approval' => true,
            ],
        ], 201);
    }
}
