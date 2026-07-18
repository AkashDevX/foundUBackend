<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a day-off schedule entry to a leave type and the leave record it creates (tenant DBs).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'leave_type_id')) {
                    $table->unsignedBigInteger('leave_type_id')->nullable()->after('status')->index();
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'leave_record_id')) {
                    $table->unsignedBigInteger('leave_record_id')->nullable()->after('leave_type_id');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) use ($connection) {
                foreach (['leave_type_id', 'leave_record_id'] as $column) {
                    if (Schema::connection($connection)->hasColumn('employee_schedule_shifts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
