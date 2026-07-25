<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee-submitted time-off requests (mobile). Admin reviews these on the
 * weekly schedule page and either approves (which creates the day off + leave
 * record) or rejects with a note. Lives in each tenant database.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('time_off_requests')) {
                continue;
            }

            Schema::connection($connection)->create('time_off_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->date('requested_date')->index();
                $table->string('reason', 500)->nullable();
                $table->string('status', 16)->default('pending')->index();
                $table->string('decision_note', 500)->nullable();
                $table->string('reviewed_by', 200)->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->unsignedBigInteger('leave_record_id')->nullable();
                $table->unsignedBigInteger('schedule_shift_id')->nullable();
                $table->timestamps();

                $table->index(['employee_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('time_off_requests');
        }
    }
};
