<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR timesheet sign-off per employee per pay week (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('timesheet_approvals')) {
                continue;
            }

            Schema::connection($connection)->create('timesheet_approvals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('week_start');
                $table->date('week_end');
                $table->unsignedInteger('total_seconds')->default(0);
                $table->unsignedSmallInteger('completed_sessions')->default(0);
                $table->string('status', 32)->default('pending')->index();
                $table->string('reviewed_by', 200)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('review_notes')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'week_start']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('timesheet_approvals');
        }
    }
};
