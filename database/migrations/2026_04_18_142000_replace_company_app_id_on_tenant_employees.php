<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registration audit fields sourced from master DB at submit time (no hardcoded org-* ids in APK).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if ($schema->hasColumn('employees', 'company_app_id')) {
                $schema->table('employees', function (Blueprint $table) {
                    $table->dropColumn('company_app_id');
                });
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'registration_company_slug')) {
                    $table->string('registration_company_slug', 120)->nullable()->after('company_display_name');
                    $table->string('registration_company_app_key', 64)->nullable()->after('registration_company_slug');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if ($schema->hasColumn('employees', 'registration_company_slug')) {
                $schema->table('employees', function (Blueprint $table) {
                    $table->dropColumn(['registration_company_slug', 'registration_company_app_key']);
                });
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'company_app_id')) {
                    $table->string('company_app_id', 64)->nullable()->after('company_display_name');
                }
            });
        }
    }
};
