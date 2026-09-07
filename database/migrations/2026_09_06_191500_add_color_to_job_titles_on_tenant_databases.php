<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional display color for job titles (list/detail accents).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('job_titles', 'color')) {
                Schema::connection($connection)->table('job_titles', function (Blueprint $table) {
                    $table->string('color', 16)->nullable()->after('award_level');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('job_titles', 'color')) {
                Schema::connection($connection)->table('job_titles', function (Blueprint $table) {
                    $table->dropColumn('color');
                });
            }
        }
    }
};
