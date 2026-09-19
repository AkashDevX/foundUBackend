<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist weekly-schedule repeat settings so edit can restore them (tenant DBs).
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
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_series_id')) {
                    $table->string('recurrence_series_id', 36)->nullable()->after('notes')->index();
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_mode')) {
                    $table->string('recurrence_mode', 32)->nullable()->after('recurrence_series_id');
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_starts')) {
                    $table->date('recurrence_starts')->nullable()->after('recurrence_mode');
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_until')) {
                    $table->date('recurrence_until')->nullable()->after('recurrence_starts');
                }
                if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_days')) {
                    $table->json('recurrence_days')->nullable()->after('recurrence_until');
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
                foreach ([
                    'recurrence_days',
                    'recurrence_until',
                    'recurrence_starts',
                    'recurrence_mode',
                    'recurrence_series_id',
                ] as $column) {
                    if (Schema::connection($connection)->hasColumn('employee_schedule_shifts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
