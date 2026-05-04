<?php

namespace App\Support;

/**
 * Maps foundU React Native payload keys (camelCase / UserProfileSnapshot) to Laravel attributes.
 */
class FoundUProfileMapper
{
    /**
     * camelCase request keys -> snake_case column / validation keys.
     *
     * @var array<string, string>
     */
    public const CAMEL_TO_SNAKE = [
        'registrationCompanySlug' => 'registration_company_slug',
        'registrationCompanyAppKey' => 'registration_company_app_key',
        'companyName' => 'company_display_name',
        'fullLegalName' => 'full_legal_name',
        'gender' => 'sex',
        'dateOfBirth' => 'date_of_birth',
        'maritalStatus' => 'marital_status',
        'emergencyContactName' => 'emergency_contact_name',
        'emergencyContactPhone' => 'emergency_contact_phone',
        'emergencyContactRelationship' => 'emergency_contact_relationship',
        'visaStatus' => 'visa_status',
        'unrestrictedWorkRights' => 'unrestricted_work_rights',
        'visaExpiry' => 'visa_expiry',
        'hoursPerWeek' => 'hours_per_week',
        'weeklyAvailabilitySummary' => 'weekly_availability_summary',
        'weeklyAvailabilityJson' => 'weekly_availability_json',
        'idDocumentsSummary' => 'id_documents_summary',
        'idDocumentsJson' => 'id_documents_json',
        'policeCheckExpiry' => 'police_check_expiry',
        'policeCheckUploaded' => 'police_check_uploaded',
        'fitToWorkExpiry' => 'fit_to_work_expiry',
        'fitToWorkUploaded' => 'fit_to_work_uploaded',
        'licencesSummary' => 'licences_summary',
        'insurancesSummary' => 'insurances_summary',
        'licencesJson' => 'licences_json',
        'insurancesJson' => 'insurances_json',
        'bankAccountName' => 'bank_account_name',
        'bankAccountNumber' => 'bank_account_number',
        'bankBranchCode' => 'bank_branch_code',
        'bankName' => 'bank_name',
        'modeOfTransport' => 'mode_of_transport',
        'vehicleRegistration' => 'vehicle_registration',
        'vehicleExpiry' => 'vehicle_expiry',
        'vehicleInsuranceUploaded' => 'vehicle_insurance_uploaded',
        'passwordConfirmation' => 'password_confirmation',
    ];

    /**
     * Split “Alex Rivera” -> first / last for legacy first_name & last_name columns.
     *
     * @return array{0: string, 1: string}
     */
    public static function splitFullLegalName(string $full): array
    {
        $full = trim($full);
        if ($full === '') {
            return ['-', '-'];
        }

        $parts = preg_split('/\s+/u', $full, 2);
        $first = $parts[0] ?? '-';
        $last = isset($parts[1]) ? trim($parts[1]) : '-';

        return [$first !== '' ? $first : '-', $last !== '' ? $last : '-'];
    }
}
