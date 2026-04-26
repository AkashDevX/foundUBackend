<?php

namespace Database\Seeders;

use App\Models\RegistrationPicklistItem;
use Illuminate\Database\Seeder;

class RegistrationPicklistsSeeder extends Seeder
{
    /**
     * Mirrors foundU wizard option lists (editable in DB without APK updates).
     *
     * @var array<string, list<array{0: string, 1?: string|null}>>
     */
    private const SETS = [
        'marital_status' => [
            ['Single'],
            ['Married'],
            ['Divorced'],
            ['Widowed'],
            ['De Facto'],
            ['Separated'],
        ],
        'visa_status' => [
            ['Australian Citizen'],
            ['Permanent Resident'],
            ['Temporary Visa - Working'],
            ['Temporary Visa - Student'],
            ['Working Holiday Visa'],
            ['Other'],
        ],
        'unrestricted_work_rights' => [
            ['Yes'],
            ['No'],
        ],
        'id_document_type' => [
            ["Driver's Licence"],
            ['Passport'],
            ['Medicare'],
            ['18+ Card'],
        ],
        'licence_type' => [
            ['RSA (Responsible Service of Alcohol)'],
            ['Forklift Licence'],
            ['First Aid'],
            ['White Card'],
            ['Other'],
        ],
        'insurance_type' => [
            ['Public Liability'],
            ['Professional Indemnity'],
            ['Workers Compensation'],
            ['Other'],
        ],
        'transport_mode' => [
            ['Own vehicle'],
            ['Public transport'],
            ['Walking'],
            ['Other'],
        ],
        'request_organization_industry' => [
            ['Healthcare'],
            ['Construction'],
            ['Retail'],
            ['Hospitality & tourism'],
            ['Manufacturing'],
            ['Education & training'],
            ['Technology & IT'],
            ['Professional services'],
            ['Government & public sector'],
            ['Not-for-profit'],
            ['Transport & logistics'],
            ['Mining & resources'],
            ['Agriculture & primary industries'],
            ['Other'],
        ],
        'request_organization_employee_band' => [
            ['1–10'],
            ['11–50'],
            ['51–200'],
            ['201–500'],
            ['501–1,000'],
            ['1,001–5,000'],
            ['5,000+'],
            ['Other'],
        ],
    ];

    public function run(): void
    {
        foreach (self::SETS as $picklistKey => $options) {
            $order = 0;
            foreach ($options as $pair) {
                $value = $pair[0];
                $label = $pair[1] ?? null;
                RegistrationPicklistItem::updateOrCreate(
                    [
                        'picklist_key' => $picklistKey,
                        'value' => $value,
                    ],
                    [
                        'label' => $label,
                        'sort_order' => $order++,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
