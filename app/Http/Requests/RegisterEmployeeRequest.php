<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\PreparesFoundURegistrationPayload;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accepts snake_case or foundU camelCase (UserProfileSnapshot) for the four-step wizard.
 */
class RegisterEmployeeRequest extends FormRequest
{
    use PreparesFoundURegistrationPayload;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareFoundURegistrationPayload();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...$this->foundURegistrationProfileRules(),
            ...$this->foundURegistrationUploadRules(),
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:employees,email'],
            'registration_company_slug' => ['required', 'string', 'max:120'],
        ];
    }
}
