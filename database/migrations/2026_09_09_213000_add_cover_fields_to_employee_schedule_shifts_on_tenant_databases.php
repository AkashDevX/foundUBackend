<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover options when a scheduled shift is marked sick call out or no show (tenant DBs).
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
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'cover_status')) {
                    $table->string('cover_status', 32)->nullable()->after('status')->index();
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'original_employee_id')) {
                    $table->unsignedBigInteger('original_employee_id')->nullable()->after('employee_id')->index();
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'covered_from_shift_id')) {
                    $table->unsignedBigInteger('covered_from_shift_id')->nullable()->after('cover_status')->index();
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'covering_shift_id')) {
                    $table->unsignedBigInteger('covering_shift_id')->nullable()->after('covered_from_shift_id');
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
                foreach (['covering_shift_id', 'covered_from_shift_id', 'original_employee_id', 'cover_status'] as $column) {
                    if (Schema::connection($connection)->hasColumn('employee_schedule_shifts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
