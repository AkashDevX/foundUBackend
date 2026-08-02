<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot whether a recorded leave is paid at the time it is taken (tenant DB).
 *
 * Captured from the leave type's `is_paid` flag so payroll treatment is stable
 * even if the leave type catalogue changes later. Defaults to true so existing
 * annual/sick leave records remain paid.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_leave_records')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('employee_leave_records', 'is_paid')) {
                continue;
            }

            Schema::connection($connection)->table('employee_leave_records', function (Blueprint $table) {
                $table->boolean('is_paid')->default(true)->after('leave_type');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasColumn('employee_leave_records', 'is_paid')) {
                continue;
            }

            Schema::connection($connection)->table('employee_leave_records', function (Blueprint $table) {
                $table->dropColumn('is_paid');
            });
        }
    }
};
