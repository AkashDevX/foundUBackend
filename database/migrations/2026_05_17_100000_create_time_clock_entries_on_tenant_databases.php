<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant punch records for mobile clock-in / clock-out with geofence audit fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            Schema::connection($connection)->create('time_clock_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('event_type', 16);
                $table->timestamp('clocked_at');
                $table->decimal('device_latitude', 10, 7);
                $table->decimal('device_longitude', 10, 7);
                $table->decimal('device_accuracy_meters', 8, 2)->nullable();
                $table->foreignId('work_location_id')->nullable()->constrained('work_locations')->nullOnDelete();
                $table->decimal('expected_latitude', 10, 7)->nullable();
                $table->decimal('expected_longitude', 10, 7)->nullable();
                $table->decimal('distance_from_site_meters', 10, 2)->nullable();
                $table->unsignedInteger('allowed_radius_meters');
                $table->boolean('within_geofence');
                $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
                $table->foreignId('shift_id')->nullable()->constrained('shifts')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'clocked_at']);
                $table->index(['employee_id', 'event_type', 'clocked_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('time_clock_entries');
        }
    }
};
