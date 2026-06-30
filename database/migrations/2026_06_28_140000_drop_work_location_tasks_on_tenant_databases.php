<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remove site-wide work_location_tasks; employee_task_assignments is the sole task store.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_task_completions')) {
                if (Schema::connection($connection)->hasColumn('employee_task_completions', 'task_source')) {
                    DB::connection($connection)
                        ->table('employee_task_completions')
                        ->where('task_source', 'site')
                        ->delete();
                }

                Schema::connection($connection)->table('employee_task_completions', function (Blueprint $table) use ($connection): void {
                    if (Schema::connection($connection)->hasColumn('employee_task_completions', 'work_location_task_id')) {
                        $table->dropForeign(['work_location_task_id']);
                        $table->dropColumn('work_location_task_id');
                    }
                });

                Schema::connection($connection)->table('employee_task_completions', function (Blueprint $table) use ($connection): void {
                    if (Schema::connection($connection)->hasColumn('employee_task_completions', 'task_source')) {
                        $table->dropUnique('employee_task_completions_site_unique');
                        $table->dropUnique('employee_task_completions_assignment_unique');
                        $table->dropColumn('task_source');
                        $table->unique(
                            ['employee_id', 'employee_task_assignment_id', 'completion_date'],
                            'employee_task_completions_assignment_unique',
                        );
                    }
                });
            }

            if (Schema::connection($connection)->hasTable('employee_task_assignments')) {
                Schema::connection($connection)->table('employee_task_assignments', function (Blueprint $table) use ($connection): void {
                    if (Schema::connection($connection)->hasColumn('employee_task_assignments', 'work_location_task_id')) {
                        $table->dropForeign(['work_location_task_id']);
                        $table->dropColumn('work_location_task_id');
                    }
                });
            }

            Schema::connection($connection)->dropIfExists('work_location_tasks');
        }
    }

    public function down(): void
    {
        // Intentionally not restored — site tasks were removed from the product.
    }
};
