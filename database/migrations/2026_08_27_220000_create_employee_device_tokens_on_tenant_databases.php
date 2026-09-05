<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FCM device tokens for employee push notifications (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_device_tokens')) {
                continue;
            }

            Schema::connection($connection)->create('employee_device_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->string('fcm_token', 512);
                $table->string('platform', 16)->default('android'); // android|ios
                $table->timestamps();

                $table->unique('fcm_token', 'employee_device_tokens_token_unique');
                $table->index(['employee_id', 'platform'], 'employee_device_tokens_employee_platform');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_device_tokens');
        }
    }
};
