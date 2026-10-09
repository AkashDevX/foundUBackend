<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-site clock geofence. Existing locations keep the previous 300 m default.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('work_locations')) {
                continue;
            }
            if ($schema->hasColumn('work_locations', 'geofence_radius_meters')) {
                continue;
            }

            $schema->table('work_locations', function (Blueprint $table) use ($connection) {
                $column = $table->unsignedInteger('geofence_radius_meters')->default(300);
                if (Schema::connection($connection)->hasColumn('work_locations', 'longitude')) {
                    $column->after('longitude');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('work_locations')) {
                continue;
            }
            if (! $schema->hasColumn('work_locations', 'geofence_radius_meters')) {
                continue;
            }

            $schema->table('work_locations', function (Blueprint $table) {
                $table->dropColumn('geofence_radius_meters');
            });
        }
    }
};
