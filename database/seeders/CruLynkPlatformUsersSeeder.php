<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\OrganizationPortalUser;
use Illuminate\Database\Seeder;

/**
 * Seeds the CruLynk platform controller portal admin (master DB).
 * Credentials differ from tenant org defaults — override via .env in production.
 */
class CruLynkPlatformUsersSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()
            ->where('slug', 'crulynk')
            ->where('is_platform_controller', true)
            ->first();

        if ($company === null) {
            return;
        }

        OrganizationPortalUser::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'email' => mb_strtolower((string) env('CRULYNK_PLATFORM_EMAIL', 'admin@crulynk.io')),
            ],
            [
                'name' => 'CruLynk Platform Admin',
                'password' => (string) env('CRULYNK_PLATFORM_PASSWORD', 'CruLynk#Platform2026'),
            ]
        );
    }
}
