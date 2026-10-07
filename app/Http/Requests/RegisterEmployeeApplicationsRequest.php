<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesFoundURegistrationPayload;
use App\Services\EmployeeRegistrationService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Platform-scoped multi-organisation join applications from Create Account.
 */
class RegisterEmployeeApplicationsRequest extends FormRequest
{
    use PreparesFoundURegistrationPayload;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFoundURegistrationPayload();

        $companies = $this->input('companies');
        if (is_string($companies)) {
            $decoded = json_decode($companies, true);
            $companies = is_array($decoded) ? $decoded : null;
        }

        if (! is_array($companies)) {
            return;
        }

        $normalized = [];
        foreach ($companies as $row) {
            if (! is_array($row)) {
                continue;
            }
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $appKey = $row['app_key'] ?? $row['appKey'] ?? null;
            $normalized[] = [
                'slug' => $slug,
                'app_key' => is_string($appKey) || $appKey === null ? $appKey : null,
            ];
        }

        $this->merge(['companies' => $normalized]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->foundURegistrationProfileRules(),
            ...$this->foundURegistrationUploadRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'companies' => ['required', 'array', 'min:1', 'max:'.EmployeeRegistrationService::MAX_COMPANIES],
            'companies.*.slug' => ['required', 'string', 'max:120'],
            'companies.*.app_key' => ['nullable', 'string', 'max:64'],
        ];
    }
}
