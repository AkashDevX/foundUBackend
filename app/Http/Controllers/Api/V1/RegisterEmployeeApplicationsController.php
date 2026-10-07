<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterEmployeeApplicationsRequest;
use App\Services\EmployeeRegistrationService;
use Illuminate\Http\JsonResponse;

/**
 * Public mobile endpoint: fan-out the Create Account profile to multiple tenant orgs.
 * Platform-scoped (X-Platform-Slug). Each tenant admin reviews their own pending employee.
 */
class RegisterEmployeeApplicationsController extends Controller
{
    public function __invoke(
        RegisterEmployeeApplicationsRequest $request,
        EmployeeRegistrationService $registration,
    ): JsonResponse {
        $payload = $registration->payloadFromValidated($request->validated());
        /** @var list<array{slug?: string, app_key?: string|null}> $companyRows */
        $companyRows = $request->validated('companies');

        $results = $registration->registerApplications(
            $request,
            $companyRows,
            $payload,
            $request->validated('full_legal_name'),
        );

        $created = array_values(array_filter(
            $results,
            static fn (array $row): bool => ($row['status'] ?? null) === EmployeeRegistrationService::STATUS_CREATED,
        ));

        $publicResults = array_map(static function (array $row): array {
            $out = [
                'slug' => $row['slug'] ?? '',
                'name' => $row['name'] ?? '',
                'status' => $row['status'] ?? EmployeeRegistrationService::STATUS_FAILED,
                'message' => $row['message'] ?? '',
            ];
            if (isset($row['public_id']) && is_string($row['public_id']) && $row['public_id'] !== '') {
                $out['public_id'] = $row['public_id'];
            }

            return $out;
        }, $results);

        if ($created === []) {
            return response()->json([
                'message' => 'None of the selected organisations could accept this application.',
                'results' => $publicResults,
            ], 422);
        }

        $createdNames = array_map(static fn (array $row): string => (string) ($row['name'] ?? $row['slug'] ?? ''), $created);
        $createdCount = count($created);
        $total = count($publicResults);

        $message = $createdCount === $total
            ? 'Applications received. Each organisation will review independently — you can sign in after an organisation approves you.'
            : sprintf(
                'Applications received for %s. Some organisations could not accept this application — check results.',
                implode(', ', $createdNames),
            );

        return response()->json([
            'message' => $message,
            'results' => $publicResults,
            'auth' => [
                'authenticated' => false,
                'token_issued' => false,
                'requires_email_password_login_after_approval' => true,
            ],
        ], 201);
    }
}
