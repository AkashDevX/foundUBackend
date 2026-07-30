<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetOtpMail;
use App\Models\Employee;
use App\Services\PasswordResetOtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Step 1: email a one-time password-reset code (OTP).
 *
 * POST /api/v1/forgot-password
 * Headers: X-Company-Slug
 * Body: { "email": "..." }
 */
class ForgotPasswordController extends Controller
{
    public function __construct(
        private readonly PasswordResetOtpService $otpService,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $company = $request->tenantCompany();
        abort_unless($company !== null, 500, 'Tenant not resolved.');

        $generic = [
            'message' => 'If an account exists for that email, we have sent a verification code. Check your inbox and spam folder.',
            'code' => 'password_reset_otp_sent',
            'expires_in_minutes' => PasswordResetOtpService::OTP_TTL_MINUTES,
        ];

        /** @var Employee|null $employee */
        $employee = Employee::query()
            ->where('email', $validated['email'])
            ->first();

        if ($employee === null || (string) ($employee->employment_status ?? '') !== 'active') {
            return response()->json($generic);
        }

        $otp = $this->otpService->issueOtp($company->slug, $validated['email']);

        try {
            Mail::to($employee->email)->send(
                new PasswordResetOtpMail(
                    $employee,
                    $company,
                    $otp,
                    PasswordResetOtpService::OTP_TTL_MINUTES,
                )
            );
        } catch (Throwable $e) {
            Log::error('Forgot password OTP email failed.', [
                'company_slug' => $company->slug,
                'employee_public_id' => $employee->public_id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not send the verification email right now. Please try again in a few minutes.',
                'code' => 'password_reset_email_failed',
            ], 503);
        }

        return response()->json($generic);
    }
}
