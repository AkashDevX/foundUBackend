<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-specific task allocations (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_task_assignments')) {
                Schema::connection($connection)->create('employee_task_assignments', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->foreignId('work_location_id')->constrained('work_locations')->cascadeOnDelete();
                    $table->string('title', 200);
                    $table->text('description')->nullable();
                    $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
                    $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
                    $table->date('scheduled_date')->nullable()->index();
                    $table->time('start_time')->nullable();
                    $table->time('end_time')->nullable();
                    $table->string('notes', 500)->nullable();
                    $table->string('created_by', 200)->nullable();
                    $table->boolean('is_active')->default(true)->index();
                    $table->timestamps();

                    $table->index(['employee_id', 'is_active']);
                    $table->index(['employee_id', 'scheduled_date']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_task_assignments');
        }
    }
};
