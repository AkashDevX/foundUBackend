<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every training module issues a certificate of completion.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                ! Schema::connection($connection)->hasTable('training_modules')
                || ! Schema::connection($connection)->hasColumn('training_modules', 'issues_certificate')
            ) {
                continue;
            }

            DB::connection($connection)->table('training_modules')->update(['issues_certificate' => true]);
            DB::connection($connection)->statement(
                'ALTER TABLE training_modules MODIFY issues_certificate TINYINT(1) NOT NULL DEFAULT 1'
            );
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                ! Schema::connection($connection)->hasTable('training_modules')
                || ! Schema::connection($connection)->hasColumn('training_modules', 'issues_certificate')
            ) {
                continue;
            }

            DB::connection($connection)->statement(
                'ALTER TABLE training_modules MODIFY issues_certificate TINYINT(1) NOT NULL DEFAULT 0'
            );
        }
    }
};
