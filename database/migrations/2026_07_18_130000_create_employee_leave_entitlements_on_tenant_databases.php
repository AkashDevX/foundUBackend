<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which leave types an individual employee is entitled to, and their yearly
 * allocation (tenant DB). Admins assign these once an employee is active.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_leave_entitlements')) {
                continue;
            }

            Schema::connection($connection)->create('employee_leave_entitlements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('leave_types')->cascadeOnDelete();
                $table->decimal('entitlement_hours', 8, 2)->nullable();
                $table->string('notes', 500)->nullable();
                $table->string('created_by', 200)->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_leave_entitlements');
        }
    }
};
