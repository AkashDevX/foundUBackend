<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Timesheet sign-off is per clock-in session (shift), not per calendar day.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('timesheet_approvals')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('timesheet_approvals', 'clock_in_entry_id')) {
                continue;
            }

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });

            if (Schema::connection($connection)->hasColumn('timesheet_approvals', 'work_date')) {
                Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                    $table->dropUnique(['employee_id', 'work_date']);
                });
            }

            DB::connection($connection)->table('timesheet_approvals')->delete();

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->foreignId('clock_in_entry_id')
                    ->after('employee_id')
                    ->constrained('time_clock_entries')
                    ->cascadeOnDelete();
                $table->unique(['employee_id', 'clock_in_entry_id']);
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('timesheet_approvals')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('timesheet_approvals', 'clock_in_entry_id')) {
                continue;
            }

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
                $table->dropForeign(['clock_in_entry_id']);
                $table->dropUnique(['employee_id', 'clock_in_entry_id']);
                $table->dropColumn('clock_in_entry_id');
            });

            DB::connection($connection)->table('timesheet_approvals')->delete();

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->unique(['employee_id', 'work_date']);
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }
};
