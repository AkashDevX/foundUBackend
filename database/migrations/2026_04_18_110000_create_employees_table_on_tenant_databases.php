<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the employees table on every configured tenant database.
 *
 * Identity is scoped per tenant (separate databases), so email uniqueness
 * is enforced within that company only.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->create('employees', function (Blueprint $table) {
                $table->id();

                // Stable external id for APIs / mobile clients (do not expose internal bigint id).
                $table->uuid('public_id')->unique();

                $table->string('employee_code', 64)->nullable()->index();

                $table->string('first_name', 120);
                $table->string('last_name', 120);
                $table->string('email');
                $table->string('phone', 32)->nullable();

                $table->string('password');

                $table->string('job_title', 160)->nullable();
                $table->string('department', 160)->nullable();

                $table->string('employment_status', 32)->default('active')->index();
                $table->date('hired_at')->nullable();

                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();

                $table->json('profile_metadata')->nullable();

                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['email']);
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employees');
        }
    }
};
