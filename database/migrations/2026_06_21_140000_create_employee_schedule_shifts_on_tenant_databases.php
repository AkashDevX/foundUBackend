<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable weekly roster shifts per employee per calendar day (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            Schema::connection($connection)->create('employee_schedule_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('scheduled_date')->index();
                $table->string('entry_type', 16)->default('shift')->index();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
                $table->foreignId('job_title_id')->nullable()->constrained('job_titles')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
                $table->string('notes', 500)->nullable();
                $table->string('created_by', 200)->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'scheduled_date']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_schedule_shifts');
        }
    }
};
