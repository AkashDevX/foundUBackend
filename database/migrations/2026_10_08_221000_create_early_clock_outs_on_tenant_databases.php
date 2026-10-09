<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Early clock-out requests. Leaving before the scheduled end has no grace
 * period: the employee adds a note and waits for an admin to approve.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('early_clock_outs')) {
                continue;
            }

            Schema::connection($connection)->create('early_clock_outs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->unsignedBigInteger('schedule_shift_id')->nullable()->index();
                $table->date('scheduled_date')->index();
                $table->timestamp('shift_ends_at');
                $table->string('status', 20)->default('pending')->index();
                $table->timestamp('attempted_at');
                $table->text('employee_note');
                $table->unsignedSmallInteger('minutes_early')->default(0);
                $table->string('cleared_by', 200)->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->text('admin_note')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'scheduled_date', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('early_clock_outs');
        }
    }
};
