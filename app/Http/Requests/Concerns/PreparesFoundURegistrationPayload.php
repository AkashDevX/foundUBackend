<?php

namespace App\Http\Requests\Concerns;

use App\Support\FoundUProfileMapper;
use App\Support\RegistrationDisplay;
use App\Support\RegistrationIdDocument;
use App\Support\RegistrationResume;
use Illuminate\Validation\Validator;

/**
 * Shared camelCase / multipart `payload` JSON handling for foundU registration.
 */
trait PreparesFoundURegistrationPayload
{
    protected function prepareFoundURegistrationPayload(): void
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

        foreach ([
            'police_check_expiry',
            'police_check_uploaded',
            'fit_to_work_expiry',
            'fit_to_work_uploaded',
            'vehicle_insurance_uploaded',
        ] as $optionalField) {
            $value = $merge[$optionalField] ?? ($this->has($optionalField) ? $this->input($optionalField) : null);
            if ($value === null) {
                continue;
            }
            if (is_string($value) && trim($value) === '') {
                $merge[$optionalField] = null;
            }
        }

        $this->merge($merge);
    }

    /**
     * Profile fields shared by single-tenant and multi-org registration.
     * Does not include email uniqueness or company slug — callers add those.
     *
     * @return array<string, mixed>
     */
    protected function foundURegistrationProfileRules(): array
    {
        return [
            'full_legal_name' => ['required', 'string', 'max:200'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],

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
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function foundURegistrationUploadRules(): array
    {
        return [
            'profile_photo' => ['nullable', 'file', 'max:15360'],
            'police_check' => ['nullable', 'file', 'max:15360'],
            'visa_document' => array_merge(['nullable'], RegistrationIdDocument::fileRules()),
            'resume' => RegistrationResume::rules(),
            'fit_to_work' => ['nullable', 'file', 'max:15360'],
            'vehicle_insurance' => array_merge(['nullable'], RegistrationIdDocument::fileRules()),
            'id_document_upload' => ['nullable', 'array'],
            'id_document_upload.*' => RegistrationIdDocument::fileRules(),
            'id_document_back_upload' => ['nullable', 'array'],
            'id_document_back_upload.*' => RegistrationIdDocument::fileRules(),
            'licence_upload' => ['nullable', 'array'],
            'licence_upload.*' => ['file', 'max:15360'],
            'insurance_upload' => ['nullable', 'array'],
            'insurance_upload.*' => ['file', 'max:15360'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = $this->input('visa_status');
            if (RegistrationDisplay::requiresVisaDocument(is_string($status) ? $status : null)
                && ! $this->hasFile('visa_document')) {
                $validator->errors()->add('visa_document', 'Upload your visa document.');
            }

            $this->requireExpiryWhenUploaded(
                $validator,
                'police_check',
                'police_check_expiry',
                'Enter the police check expiry date.',
            );
            $this->requireExpiryWhenUploaded(
                $validator,
                'fit_to_work',
                'fit_to_work_expiry',
                'Enter the fit to work certificate expiry date.',
            );
        });
    }

    private function requireExpiryWhenUploaded(
        Validator $validator,
        string $fileField,
        string $expiryField,
        string $message,
    ): void {
        if (! $this->hasFile($fileField)) {
            return;
        }

        $raw = $this->input($expiryField);
        $iso = RegistrationDisplay::toNullableIsoDate(is_scalar($raw) ? $raw : null);
        if ($iso === null) {
            $validator->errors()->add($expiryField, $message);
        }
    }
}
