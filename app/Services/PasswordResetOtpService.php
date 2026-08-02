<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Tenant-scoped password-reset OTPs and short-lived reset tokens.
 * Uses the file cache so tenant DB switching does not affect storage.
 */
class PasswordResetOtpService
{
    public const OTP_TTL_MINUTES = 15;

    public const RESET_TOKEN_TTL_MINUTES = 15;

    public const MAX_OTP_ATTEMPTS = 5;

    public function issueOtp(string $companySlug, string $email): string
    {
        $otp = (string) random_int(100000, 999999);

        Cache::store('file')->put(
            $this->otpKey($companySlug, $email),
            [
                'hash' => Hash::make($otp),
                'attempts' => 0,
            ],
            now()->addMinutes(self::OTP_TTL_MINUTES)
        );

        return $otp;
    }

    /**
     * @return array{ok: true}|array{ok: false, code: string, message: string}
     */
    public function verifyOtp(string $companySlug, string $email, string $otp): array
    {
        $key = $this->otpKey($companySlug, $email);
        $payload = Cache::store('file')->get($key);

        if (! is_array($payload) || ! isset($payload['hash'])) {
            return [
                'ok' => false,
                'code' => 'otp_invalid',
                'message' => 'That code is invalid or has expired. Request a new one.',
            ];
        }

        $attempts = (int) ($payload['attempts'] ?? 0);
        if ($attempts >= self::MAX_OTP_ATTEMPTS) {
            Cache::store('file')->forget($key);

            return [
                'ok' => false,
                'code' => 'otp_locked',
                'message' => 'Too many incorrect attempts. Please request a new code.',
            ];
        }

        if (! Hash::check($otp, (string) $payload['hash'])) {
            $payload['attempts'] = $attempts + 1;
            Cache::store('file')->put($key, $payload, now()->addMinutes(self::OTP_TTL_MINUTES));

            return [
                'ok' => false,
                'code' => 'otp_invalid',
                'message' => 'That code is incorrect. Please try again.',
            ];
        }

        Cache::store('file')->forget($key);

        return ['ok' => true];
    }

    public function issueResetToken(string $companySlug, string $email, int $employeeId): string
    {
        $token = Str::random(64);

        Cache::store('file')->put(
            $this->resetTokenKey($token),
            [
                'company_slug' => $companySlug,
                'email' => strtolower(trim($email)),
                'employee_id' => $employeeId,
            ],
            now()->addMinutes(self::RESET_TOKEN_TTL_MINUTES)
        );

        return $token;
    }

    /**
     * @return array{company_slug: string, email: string, employee_id: int}|null
     */
    public function consumeResetToken(string $token): ?array
    {
        $key = $this->resetTokenKey($token);
        $payload = Cache::store('file')->pull($key);

        if (! is_array($payload)) {
            return null;
        }

        $slug = $payload['company_slug'] ?? null;
        $email = $payload['email'] ?? null;
        $employeeId = $payload['employee_id'] ?? null;

        if (! is_string($slug) || ! is_string($email) || ! is_numeric($employeeId)) {
            return null;
        }

        return [
            'company_slug' => $slug,
            'email' => $email,
            'employee_id' => (int) $employeeId,
        ];
    }

    private function otpKey(string $companySlug, string $email): string
    {
        return 'pwd_otp:'.strtolower($companySlug).':'.strtolower(trim($email));
    }

    private function resetTokenKey(string $token): string
    {
        return 'pwd_reset_token:'.$token;
    }
}
