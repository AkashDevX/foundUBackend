<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('work_locations')) {
                continue;
            }
            if (Schema::connection($connection)->hasColumn('work_locations', 'latitude')) {
                continue;
            }

            Schema::connection($connection)->table('work_locations', function (Blueprint $table) {
                $table->decimal('latitude', 10, 7)->nullable()->after('address');
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->table('work_locations', function (Blueprint $table) {
                $table->dropColumn(['latitude', 'longitude']);
            });
        }
    }
};
