<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Step 2: verify the emailed OTP and issue a short-lived reset token.
 *
 * POST /api/v1/forgot-password/verify-otp
 * Headers: X-Company-Slug
 * Body: { "email": "...", "otp": "123456" }
 */
class VerifyPasswordResetOtpController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $otpService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'otp' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', $validated['email'])
            ->first();

        if ($employee === null || (string) ($employee->employment_status ?? '') !== 'active') {
            return response()->json([
                'message' => 'That code is invalid or has expired. Request a new one.',
                'code' => 'otp_invalid',
            ], 422);
        }

        $result = $this->otpService->verifyOtp($company->slug, $validated['email'], $validated['otp']);
        if ($result['ok'] !== true) {
            return response()->json([
                'message' => $result['message'],
                'code' => $result['code'],
            ], 422);
        }

        $resetToken = $this->otpService->issueResetToken(
            $company->slug,
            $validated['email'],
            (int) $employee->id,
        );

        return response()->json([
            'message' => 'Code verified. You can set a new password now.',
            'code' => 'otp_verified',
            'reset_token' => $resetToken,
            'expires_in_minutes' => PasswordResetOtpService::RESET_TOKEN_TTL_MINUTES,
        ]);
    }
}
