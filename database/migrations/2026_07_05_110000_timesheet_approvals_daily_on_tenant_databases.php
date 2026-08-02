<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Timesheet sign-off is per employee per calendar day (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('timesheet_approvals')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('timesheet_approvals', 'work_date')) {
                continue;
            }

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropUnique(['employee_id', 'week_start']);
            });

            DB::connection($connection)->table('timesheet_approvals')->delete();

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->renameColumn('week_start', 'work_date');
                $table->dropColumn('week_end');
            });

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->unique(['employee_id', 'work_date']);
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

            if (! Schema::connection($connection)->hasColumn('timesheet_approvals', 'work_date')) {
                continue;
            }

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropForeign(['employee_id']);
            });

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->dropUnique(['employee_id', 'work_date']);
            });

            DB::connection($connection)->table('timesheet_approvals')->delete();

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->renameColumn('work_date', 'week_start');
                $table->date('week_end')->nullable();
            });

            Schema::connection($connection)->table('timesheet_approvals', function (Blueprint $table) {
                $table->unique(['employee_id', 'week_start']);
                $table->foreign('employee_id')->references('id')->on('employees')->cascadeOnDelete();
            });
        }
    }
};
