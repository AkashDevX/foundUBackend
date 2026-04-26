<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Paths are relative to the employee_registration disk (storage/app/employee_registration).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('employees')) {
                continue;
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'police_check_path')) {
                    $table->string('police_check_path', 512)->nullable()->after('police_check_uploaded');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'fit_to_work_path')) {
                    $table->string('fit_to_work_path', 512)->nullable()->after('fit_to_work_uploaded');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'vehicle_insurance_path')) {
                    $table->string('vehicle_insurance_path', 512)->nullable()->after('vehicle_insurance_uploaded');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('employees')) {
                continue;
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                $cols = [];
                if (Schema::connection($connection)->hasColumn('employees', 'police_check_path')) {
                    $cols[] = 'police_check_path';
                }
                if (Schema::connection($connection)->hasColumn('employees', 'fit_to_work_path')) {
                    $cols[] = 'fit_to_work_path';
                }
                if (Schema::connection($connection)->hasColumn('employees', 'vehicle_insurance_path')) {
                    $cols[] = 'vehicle_insurance_path';
                }
                if ($cols !== []) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
