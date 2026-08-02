<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remove the unused leave loading column from employee leave records (tenant DB).
 *
 * Leave is paid at the ordinary rate; leave loading is not part of the product.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasColumn('employee_leave_records', 'loading_percent')) {
                continue;
            }

            Schema::connection($connection)->table('employee_leave_records', function (Blueprint $table) {
                $table->dropColumn('loading_percent');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_leave_records')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('employee_leave_records', 'loading_percent')) {
                continue;
            }

            Schema::connection($connection)->table('employee_leave_records', function (Blueprint $table) {
                $table->decimal('loading_percent', 5, 2)->nullable()->after('hourly_rate');
            });
        }
    }
};
