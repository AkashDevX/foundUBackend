<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Path is relative to the employee_registration disk (storage/app/employee_registration).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('employees')) {
                continue;
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'visa_document_path')) {
                    if (Schema::connection($connection)->hasColumn('employees', 'visa_expiry')) {
                        $table->string('visa_document_path', 512)->nullable()->after('visa_expiry');
                    } else {
                        $table->string('visa_document_path', 512)->nullable();
                    }
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            $schema = Schema::connection($connection);
            if (! $schema->hasTable('employees')) {
                continue;
            }
            $schema->table('employees', function (Blueprint $table) use ($connection) {
                if (Schema::connection($connection)->hasColumn('employees', 'visa_document_path')) {
                    $table->dropColumn('visa_document_path');
                }
            });
        }
    }
};
