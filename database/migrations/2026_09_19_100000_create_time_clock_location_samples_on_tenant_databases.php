<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mid-shift GPS samples while an employee is clocked in (keyed to clock-in entry id).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('time_clock_location_samples')) {
                continue;
            }

            Schema::connection($connection)->create('time_clock_location_samples', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->unsignedBigInteger('clock_in_entry_id');
                $table->timestamp('recorded_at');
                $table->decimal('latitude', 10, 7);
                $table->decimal('longitude', 10, 7);
                $table->decimal('accuracy_meters', 8, 2)->nullable();
                $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
                $table->decimal('distance_from_site_meters', 10, 2)->nullable();
                $table->unsignedInteger('allowed_radius_meters')->nullable();
                $table->boolean('within_geofence')->default(false);
                $table->timestamps();

                $table->foreign('clock_in_entry_id')
                    ->references('id')
                    ->on('time_clock_entries')
                    ->cascadeOnDelete();

                $table->index(['employee_id', 'recorded_at']);
                $table->index(['clock_in_entry_id', 'recorded_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('time_clock_location_samples');
        }
    }
};
