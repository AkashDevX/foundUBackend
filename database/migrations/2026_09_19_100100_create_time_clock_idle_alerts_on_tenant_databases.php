<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Low-movement (idle) alerts detected from mid-shift location samples.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('time_clock_idle_alerts')) {
                continue;
            }

            Schema::connection($connection)->create('time_clock_idle_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->unsignedBigInteger('clock_in_entry_id');
                $table->timestamp('started_at');
                $table->timestamp('detected_at');
                $table->unsignedInteger('idle_minutes');
                $table->decimal('center_latitude', 10, 7)->nullable();
                $table->decimal('center_longitude', 10, 7)->nullable();
                $table->decimal('max_displacement_meters', 10, 2)->nullable();
                $table->string('status', 16)->default('open');
                $table->timestamp('employee_acknowledged_at')->nullable();
                $table->timestamp('cleared_at')->nullable();
                $table->timestamps();

                $table->foreign('clock_in_entry_id')
                    ->references('id')
                    ->on('time_clock_entries')
                    ->cascadeOnDelete();

                $table->index(['employee_id', 'status', 'detected_at']);
                $table->index(['clock_in_entry_id', 'status']);
                $table->index(['status', 'detected_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('time_clock_idle_alerts');
        }
    }
};
