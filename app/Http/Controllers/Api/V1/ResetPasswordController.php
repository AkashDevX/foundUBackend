<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;

/**
 * Step 3: set a new password using the short-lived reset token from OTP verification.
 *
 * POST /api/v1/forgot-password/reset
 * Headers: X-Company-Slug
 * Body: { "reset_token": "...", "password": "...", "password_confirmation": "..." }
 */
class ResetPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $otpService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reset_token' => ['required', 'string', 'min:40', 'max:128'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        $payload = $this->otpService->consumeResetToken($validated['reset_token']);
        if ($payload === null) {
            return response()->json([
                'message' => 'This reset session has expired. Please request a new verification code.',
                'code' => 'reset_token_invalid',
            ], 422);
        }

        if ($payload['company_slug'] !== $company->slug) {
            return response()->json([
                'message' => 'This reset session has expired. Please request a new verification code.',
                'code' => 'reset_token_invalid',
            ], 422);
        }

        /** @var Employee|null $employee */
        $employee = Employee::query()->find($payload['employee_id']);
        if (
            $employee === null
            || strtolower((string) $employee->email) !== strtolower($payload['email'])
            || (string) ($employee->employment_status ?? '') !== 'active'
        ) {
            return response()->json([
                'message' => 'This reset session has expired. Please request a new verification code.',
                'code' => 'reset_token_invalid',
            ], 422);
        }

        $employee->forceFill([
            'password' => $validated['password'],
        ])->save();

        $employee->tokens()->delete();

        return response()->json([
            'message' => 'Your password has been updated. You can sign in with your new password.',
            'code' => 'password_reset_complete',
        ]);
    }
}
