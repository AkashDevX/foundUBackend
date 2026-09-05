<?php

namespace App\Services;

use App\Models\EmployeeDeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends FCM HTTP v1 push notifications using a Google service-account JSON.
 * Does not store chat data in Firebase — only delivers wake-up pings.
 */
class FcmPushService
{
    private ?string $cachedAccessToken = null;

    private ?int $cachedAccessTokenExpiresAt = null;

    public function isEnabled(): bool
    {
        if (! filter_var(env('FCM_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return false;
        }

        $path = $this->credentialsPath();
        $projectId = trim((string) env('FCM_PROJECT_ID', ''));

        return $projectId !== '' && $path !== null && is_readable($path);
    }

    /**
     * @param  list<int>  $employeeIds
     * @param  array{title: string, body: string, data?: array<string, string>}  $payload
     */
    public function sendToEmployees(array $employeeIds, array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        if ($employeeIds === []) {
            return;
        }

        $tokens = EmployeeDeviceToken::query()
            ->whereIn('employee_id', $employeeIds)
            ->pluck('fcm_token')
            ->filter(fn ($t) => is_string($t) && trim($t) !== '')
            ->unique()
            ->values()
            ->all();

        foreach ($tokens as $token) {
            $this->sendToToken((string) $token, $payload);
        }
    }

    /**
     * @param  array{title: string, body: string, data?: array<string, string>}  $payload
     */
    public function sendToToken(string $token, array $payload): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $projectId = trim((string) env('FCM_PROJECT_ID', ''));
        $accessToken = $this->accessToken();
        if ($accessToken === null) {
            return;
        }

        $data = [];
        foreach (($payload['data'] ?? []) as $key => $value) {
            $data[(string) $key] = (string) $value;
        }

        $body = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $payload['title'],
                    'body' => $payload['body'],
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'channel_id' => 'chat_messages',
                        'sound' => 'default',
                    ],
                ],
                'apns' => [
                    'payload' => [
                        'aps' => [
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $res = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout(12)
                ->post(
                    "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send",
                    $body,
                );

            if ($res->status() === 404 || $res->status() === 410) {
                EmployeeDeviceToken::query()->where('fcm_token', $token)->delete();

                return;
            }

            if (! $res->successful()) {
                Log::warning('FCM send failed', [
                    'status' => $res->status(),
                    'body' => $res->body(),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('FCM send exception: '.$e->getMessage());
        }
    }

    private function credentialsPath(): ?string
    {
        $configured = trim((string) env('FCM_CREDENTIALS', 'storage/app/firebase/service-account.json'));
        if ($configured === '') {
            return null;
        }

        if (str_starts_with($configured, '/') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $configured) === 1) {
            return $configured;
        }

        return base_path($configured);
    }

    private function accessToken(): ?string
    {
        if (
            $this->cachedAccessToken !== null
            && $this->cachedAccessTokenExpiresAt !== null
            && time() < ($this->cachedAccessTokenExpiresAt - 60)
        ) {
            return $this->cachedAccessToken;
        }

        $path = $this->credentialsPath();
        if ($path === null || ! is_readable($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);
        if (! is_array($json)) {
            Log::warning('FCM credentials JSON invalid');

            return null;
        }

        $clientEmail = $json['client_email'] ?? null;
        $privateKey = $json['private_key'] ?? null;
        if (! is_string($clientEmail) || ! is_string($privateKey)) {
            Log::warning('FCM credentials missing client_email/private_key');

            return null;
        }

        $now = time();
        $jwtHeader = $this->base64UrlEncode((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $jwtClaim = $this->base64UrlEncode((string) json_encode([
            'iss' => $clientEmail,
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $now + 3600,
        ]));
        $unsigned = $jwtHeader.'.'.$jwtClaim;

        $signature = '';
        $ok = openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256);
        if (! $ok) {
            Log::warning('FCM JWT sign failed');

            return null;
        }

        $assertion = $unsigned.'.'.$this->base64UrlEncode($signature);

        try {
            $res = Http::asForm()
                ->timeout(12)
                ->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $assertion,
                ]);

            if (! $res->successful()) {
                Log::warning('FCM OAuth token failed', ['status' => $res->status(), 'body' => $res->body()]);

                return null;
            }

            $accessToken = $res->json('access_token');
            $expiresIn = (int) $res->json('expires_in', 3600);
            if (! is_string($accessToken) || $accessToken === '') {
                return null;
            }

            $this->cachedAccessToken = $accessToken;
            $this->cachedAccessTokenExpiresAt = time() + max(60, $expiresIn);

            return $accessToken;
        } catch (\Throwable $e) {
            Log::warning('FCM OAuth exception: '.$e->getMessage());

            return null;
        }
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
