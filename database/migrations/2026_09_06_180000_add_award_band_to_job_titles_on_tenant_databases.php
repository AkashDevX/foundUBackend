<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link each job title to a Cleaning Award band (employment type × award level).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            Schema::connection($connection)->table('job_titles', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('job_titles', 'employment_type')) {
                    $table->string('employment_type', 32)->nullable()->after('name')->index();
                }
                if (! Schema::connection($connection)->hasColumn('job_titles', 'award_level')) {
                    $table->string('award_level', 32)->nullable()->after('employment_type')->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            Schema::connection($connection)->table('job_titles', function (Blueprint $table) use ($connection) {
                if (Schema::connection($connection)->hasColumn('job_titles', 'award_level')) {
                    $table->dropColumn('award_level');
                }
                if (Schema::connection($connection)->hasColumn('job_titles', 'employment_type')) {
                    $table->dropColumn('employment_type');
                }
            });
        }
    }
};
