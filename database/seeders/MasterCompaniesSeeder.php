<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class MasterCompaniesSeeder extends Seeder
{
    /**
     * Seed the master registry with the initial tenant databases.
     */
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Blue Green Facility Services',
                'slug' => 'bluegreen-facility-services',
                'app_key' => 'org-demo',
                'database_name' => 'bluegreenfacilityservicesdb',
                'tenant_connection' => 'tenant_bluegreen',
            ],
            [
                'name' => 'Construct Concepts',
                'slug' => 'construct-concepts',
                'app_key' => 'org-health',
                'database_name' => 'constructconceptsdb',
                'tenant_connection' => 'tenant_constructconcepts',
            ],
            [
                'name' => 'Aid and Able Services',
                'slug' => 'aid-and-able-services',
                'app_key' => 'org-retail',
                'database_name' => 'aidandableservicesdb',
                'tenant_connection' => 'tenant_aidandable',
            ],
        ];

        foreach ($rows as $row) {
            Company::updateOrCreate(
                ['slug' => $row['slug']],
                $row + ['is_active' => true]
            );
        }
    }
}
