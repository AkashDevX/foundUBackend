<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-organization clock-in grace settings and the exception queue an admin
 * clears before an employee can punch outside that window.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_settings')) {
                Schema::connection($connection)->create('time_clock_settings', function (Blueprint $table) {
                    $table->id();
                    $table->unsignedSmallInteger('grace_minutes')->default(20);
                    $table->string('outside_policy', 20)->default('exception');
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('clock_in_exceptions')) {
                Schema::connection($connection)->create('clock_in_exceptions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->unsignedBigInteger('schedule_shift_id')->nullable()->index();
                    $table->date('scheduled_date')->index();
                    $table->timestamp('shift_starts_at');
                    $table->string('kind', 10);
                    $table->string('status', 20)->default('pending')->index();
                    $table->timestamp('attempted_at');
                    $table->unsignedSmallInteger('grace_minutes');
                    $table->unsignedSmallInteger('minutes_outside')->default(0);
                    $table->string('cleared_by', 200)->nullable();
                    $table->timestamp('cleared_at')->nullable();
                    $table->text('admin_note')->nullable();
                    $table->timestamps();

                    $table->index(['employee_id', 'scheduled_date', 'status']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('clock_in_exceptions');
            Schema::connection($connection)->dropIfExists('time_clock_settings');
        }
    }
};
