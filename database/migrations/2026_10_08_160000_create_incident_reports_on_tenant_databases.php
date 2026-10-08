<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Workplace incident reports submitted from the mobile app.
 * Lives in each tenant database so the organization admin can review them.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('incident_reports')) {
                continue;
            }

            Schema::connection($connection)->create('incident_reports', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('status', 20)->default('new')->index();
                $table->string('incident_type', 40);
                $table->string('site_name', 200);
                $table->string('location', 255);
                $table->timestamp('occurred_at')->nullable()->index();
                $table->text('summary');
                $table->string('witnesses', 1000)->nullable();
                $table->string('reported_to', 200)->nullable();
                $table->json('details')->nullable();
                $table->string('attachment_path')->nullable();
                $table->string('attachment_name')->nullable();
                $table->string('attachment_mime', 120)->nullable();
                $table->text('admin_note')->nullable();
                $table->string('reviewed_by', 200)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('incident_reports');
        }
    }
};
