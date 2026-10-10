<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shifts marked "Make available" can be requested by any employee.
 * made_available stays true after an admin assigns the shift, so the offer
 * remains visible as already taken.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_schedule_shifts')
                && ! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'made_available')) {
                Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) {
                    $table->boolean('made_available')->default(false)->after('cover_status')->index();
                });
            }

            if (Schema::connection($connection)->hasTable('available_shift_requests')) {
                continue;
            }

            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            Schema::connection($connection)->create('available_shift_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('schedule_shift_id')->constrained('employee_schedule_shifts')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('note', 500);
                $table->string('status', 16)->default('pending')->index();
                $table->string('decision_note', 500)->nullable();
                $table->string('reviewed_by', 200)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['schedule_shift_id', 'status']);
                $table->index(['employee_id', 'schedule_shift_id']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('available_shift_requests');

            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'made_available')) {
                Schema::connection($connection)->table('employee_schedule_shifts', function (Blueprint $table) {
                    $table->dropColumn('made_available');
                });
            }
        }
    }
};
