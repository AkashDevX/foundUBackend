<?php

namespace App\Http\Requests;

use App\Models\RegistrationPicklistItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Mobile: POST /api/v1/request-organization — all fields mandatory.
 */
class StoreOrganizationRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'company_name',
            'industry',
            'industry_other',
            'employee_band',
            'employee_band_other',
            'postcode',
            'contact_full_name',
            'contact_email',
            'contact_telephone',
        ];

        $merge = [];
        foreach ($fields as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $merge[$key] = trim($value);
            }
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $industryValues = RegistrationPicklistItem::activeValues('request_organization_industry');
        $employeeBandValues = RegistrationPicklistItem::activeValues('request_organization_employee_band');

        return [
            'company_name' => ['required', 'string', 'min:1', 'max:200'],
            'industry' => ['required', 'string', Rule::in($industryValues)],
            'industry_other' => ['required_if:industry,Other', 'nullable', 'string', 'min:1', 'max:255'],
            'employee_band' => ['required', 'string', Rule::in($employeeBandValues)],
            'employee_band_other' => ['required_if:employee_band,Other', 'nullable', 'string', 'min:1', 'max:64'],
            'postcode' => ['required', 'string', 'min:1', 'max:32'],
            'contact_full_name' => ['required', 'string', 'min:1', 'max:200'],
            'contact_email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'contact_telephone' => ['required', 'string', 'min:1', 'max:48'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.required' => 'Company name is required.',
            'industry.required' => 'Industry is required.',
            'industry.in' => 'Select a valid industry.',
            'industry_other.required_if' => 'Describe your industry when Other is selected.',
            'employee_band.required' => 'Number of employees is required.',
            'employee_band.in' => 'Select a valid employee count range.',
            'employee_band_other.required_if' => 'Enter an approximate employee count when Other is selected.',
            'postcode.required' => 'Company postcode is required.',
            'contact_full_name.required' => 'Full name is required.',
            'contact_email.required' => 'Company email address is required.',
            'contact_email.email' => 'Enter a valid company email address.',
            'contact_telephone.required' => 'Telephone number is required.',
        ];
    }
}
