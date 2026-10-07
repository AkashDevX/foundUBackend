<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveTenantFromMaster;
use App\Http\Requests\RegisterEmployeeRequest;
use App\Models\Employee;
use App\Services\EmployeeRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * Persists the full four-step foundU registration into the tenant `employees` table.
 * Tenant is resolved by X-Company-Slug via {@see ResolveTenantFromMaster}.
 */
class RegisterEmployeeController extends Controller
{
    public function __invoke(RegisterEmployeeRequest $request, EmployeeRegistrationService $registration): JsonResponse
    {
        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        if ($request->validated('registration_company_slug') !== $company->slug) {
            return response()->json([
                'message' => 'Selected company does not match X-Company-Slug (tenant).',
            ], 422);
        }

        if (! $registration->appKeyIsValid($company, $request->validated('registration_company_app_key'))) {
            return response()->json([
                'message' => 'Organization credentials do not match the master registry. Use GET /api/v1/bootstrap and send registration_company_app_key equal to the company appKey.',
                'code' => 'invalid_organization_key',
            ], 422);
        }

        $payload = $registration->payloadFromValidated($request->validated());
        $result = $registration->createPendingEmployee(
            $company,
            $request,
            $payload,
            $request->validated('full_legal_name'),
        );

        if (($result['status'] ?? null) !== EmployeeRegistrationService::STATUS_CREATED) {
            return response()->json([
                'message' => $result['message'] ?? 'Registration failed.',
            ], 422);
        }

        /** @var Employee $employee */
        $employee = $result['employee'];

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
