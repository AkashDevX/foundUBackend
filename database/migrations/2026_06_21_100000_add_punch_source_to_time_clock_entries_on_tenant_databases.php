<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Distinguish manual punches from automatic geofence-exit clock-outs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('time_clock_entries', 'punch_source')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->string('punch_source', 32)->default('manual')->after('within_geofence');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('time_clock_entries', 'punch_source')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->dropColumn('punch_source');
            });
        }
    }
};
