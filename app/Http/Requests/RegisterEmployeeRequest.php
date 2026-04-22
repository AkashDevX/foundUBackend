<?php

namespace App\Http\Requests;

use App\Support\FoundUProfileMapper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Accepts snake_case or foundU camelCase (UserProfileSnapshot) for the four-step wizard.
 */
class RegisterEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('payload')) {
            $raw = $this->input('payload');
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $this->merge($decoded);
                }
            }
        }

        $merge = [];
        foreach (FoundUProfileMapper::CAMEL_TO_SNAKE as $camel => $snake) {
            if (array_key_exists($camel, $this->all())) {
                $merge[$snake] = $this->input($camel);
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_legal_name' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:employees,email'],
            'password' => ['required', 'confirmed', Password::defaults()],

            'registration_company_slug' => ['required', 'string', 'max:120'],
            'registration_company_app_key' => ['nullable', 'string', 'max:64'],
            'company_display_name' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:48'],
            'date_of_birth' => ['nullable', 'string', 'max:32'],
            'sex' => ['nullable', 'string', 'max:16'],
            'marital_status' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:48'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:120'],

            'visa_status' => ['nullable', 'string', 'max:120'],
            'unrestricted_work_rights' => ['nullable', 'string', 'max:8'],
            'visa_expiry' => ['nullable', 'string', 'max:32'],
            'hours_per_week' => ['nullable', 'string', 'max:16'],
            'weekly_availability_summary' => ['nullable', 'string', 'max:5000'],
            'weekly_availability_json' => ['nullable', 'array'],
            'id_documents_summary' => ['nullable', 'string', 'max:5000'],
            'id_documents_json' => ['nullable', 'array'],

            'police_check_expiry' => ['nullable', 'string', 'max:32'],
            'police_check_uploaded' => ['nullable', 'string', 'max:8'],
            'fit_to_work_expiry' => ['nullable', 'string', 'max:32'],
            'fit_to_work_uploaded' => ['nullable', 'string', 'max:8'],
            'licences_summary' => ['nullable', 'string', 'max:5000'],
            'insurances_summary' => ['nullable', 'string', 'max:5000'],
            'licences_json' => ['nullable', 'array'],
            'insurances_json' => ['nullable', 'array'],

            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'string', 'max:500'],
            'bank_branch_code' => ['nullable', 'string', 'max:32'],
            'bank_name' => ['nullable', 'string', 'max:160'],
            'mode_of_transport' => ['nullable', 'string', 'max:64'],
            'vehicle_registration' => ['nullable', 'string', 'max:64'],
            'vehicle_expiry' => ['nullable', 'string', 'max:32'],
            'vehicle_insurance_uploaded' => ['nullable', 'string', 'max:8'],

            'employee_code' => ['nullable', 'string', 'max:64'],
            'job_title' => ['nullable', 'string', 'max:160'],
            'department' => ['nullable', 'string', 'max:160'],

            'payload' => ['nullable', 'string'],

            'profile_photo' => ['nullable', 'file', 'max:15360'],
            'police_check' => ['nullable', 'file', 'max:15360'],
            'fit_to_work' => ['nullable', 'file', 'max:15360'],
            'vehicle_insurance' => ['nullable', 'file', 'max:15360'],
            'id_document_upload' => ['nullable', 'array'],
            'id_document_upload.*' => ['file', 'max:15360'],
            'licence_upload' => ['nullable', 'array'],
            'licence_upload.*' => ['file', 'max:15360'],
            'insurance_upload' => ['nullable', 'array'],
            'insurance_upload.*' => ['file', 'max:15360'],
        ];
    }
}
