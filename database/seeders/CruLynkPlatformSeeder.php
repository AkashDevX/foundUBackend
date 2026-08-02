<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CruLynkPlatformSeeder extends Seeder
{
    /**
     * Seed the CruLynk platform controller organization (master DB only — no tenant database).
     */
    public function run(): void
    {
        Company::updateOrCreate(
            ['slug' => 'crulynk'],
            [
                'name' => 'CruLynk',
                'app_key' => null,
                'database_name' => null,
                'tenant_connection' => null,
                'is_active' => true,
                'is_platform_controller' => true,
            ]
        );
    }
}
