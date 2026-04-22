<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Mobile app login: same tenant header as registration (X-Company-Slug).
 * Only employees with employment_status active may receive a Bearer token.
 *
 * React Native: POST /api/v1/login with headers
 *   X-Company-Slug: {slug from bootstrap}
 *   Content-Type: application/json
 * Body: { "email": "...", "password": "..." }
 * Response: { "token", "auth": { "authenticated": true, "token_issued": true }, "employee": { ... } }
 * The only way to get a token is a successful email+password check for an active account.
 * Do not auto-enter the app after registration or after approval — wait for user to submit login.
 */
class LoginEmployeeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:500'],
        ]);

        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', $credentials['email'])
            ->first();

        if ($employee === null || ! Hash::check($credentials['password'], $employee->password)) {
            return response()->json([
                'message' => 'Invalid email or password.',
                'code' => 'invalid_credentials',
            ], 401);
        }

        $status = (string) ($employee->employment_status ?? '');

        if ($status === 'pending') {
            return response()->json([
                'message' => 'Your application is still being reviewed by your organization. You can sign in here after an administrator approves your registration.',
                'code' => 'pending_approval',
            ], 403);
        }

        if (in_array($status, ['declined', 'rejected'], true)) {
            return response()->json([
                'message' => 'This registration was not approved. Please contact your organization if you need help.',
                'code' => 'registration_declined',
            ], 403);
        }

        if ($status !== 'active') {
            return response()->json([
                'message' => 'This account is not active. Please contact your organization.',
                'code' => 'account_inactive',
            ], 403);
        }

        $employee->forceFill(['last_login_at' => now()])->save();

        $employee->loadMissing(['assignedDepartment', 'workLocation', 'assignedShift']);

        $plainToken = $employee->createToken('mobile')->plainTextToken;

        return response()->json([
            'token' => $plainToken,
            'token_type' => 'Bearer',
            'auth' => [
                'authenticated' => true,
                'token_issued' => true,
                'via' => 'email_password',
            ],
            'employee' => $this->employeePayload($employee),
        ]);
    }

    private function employeePayload(Employee $employee): array
    {
        return [
            'public_id' => $employee->public_id,
            'email' => $employee->email,
            'full_legal_name' => $employee->full_legal_name,
            'first_name' => $employee->first_name,
            'last_name' => $employee->last_name,
            'employment_status' => $employee->employment_status,
            'company_display_name' => $employee->company_display_name,
            'work_assignment' => $employee->workAssignmentForApi(),
        ];
    }
}
