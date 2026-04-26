<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\OrganizationPortalUser;
use Illuminate\Database\Seeder;

/**
 * Seeds one portal admin per registered company (master DB).
 * Default password for all: <strong>password</strong> — change in production.
 */
class OrganizationPortalUsersSeeder extends Seeder
{
    public function run(): void
    {
        $companies = Company::query()->where('is_active', true)->get();

        foreach ($companies as $company) {
            OrganizationPortalUser::query()->updateOrCreate(
                [
                    'company_id' => $company->id,
                    'email' => 'admin@foundu.local',
                ],
                [
                    'name' => $company->name.' Admin',
                    'password' => 'password',
                ]
            );
        }
    }
}
