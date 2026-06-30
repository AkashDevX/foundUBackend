<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(MasterCompaniesSeeder::class);
        $this->call(CruLynkPlatformSeeder::class);
        $this->call(RegistrationPicklistsSeeder::class);
        $this->call(OrganizationPortalUsersSeeder::class);
        $this->call(CruLynkPlatformUsersSeeder::class);

        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
