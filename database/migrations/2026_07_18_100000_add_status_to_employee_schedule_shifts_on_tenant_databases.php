<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance status for a scheduled shift (e.g. sick call out, no show) on tenant DBs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'status')) {
                continue;
            }

            Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) {
                $table->string('status', 32)->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'status')) {
                continue;
            }

            Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) {
                $table->dropColumn('status');
            });
        }
    }
};
