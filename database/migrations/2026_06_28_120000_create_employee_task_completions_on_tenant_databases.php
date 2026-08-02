<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-day task completion records for mobile employees (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_task_completions')) {
                continue;
            }

            Schema::connection($connection)->create('employee_task_completions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('employee_task_assignment_id')->constrained('employee_task_assignments')->cascadeOnDelete();
                $table->date('completion_date')->index();
                $table->timestamp('completed_at');
                $table->timestamps();

                $table->unique(
                    ['employee_id', 'employee_task_assignment_id', 'completion_date'],
                    'employee_task_completions_assignment_unique',
                );
                $table->index(['employee_id', 'completion_date']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_task_completions');
        }
    }
};
