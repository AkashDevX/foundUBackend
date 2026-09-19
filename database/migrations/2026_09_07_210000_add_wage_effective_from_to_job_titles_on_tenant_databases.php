<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Date the job title hourly wage takes effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('job_titles', 'wage_effective_from')) {
                Schema::connection($connection)->table('job_titles', function (Blueprint $table) {
                    $table->date('wage_effective_from')->nullable()->after('hourly_wage');
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

            if (Schema::connection($connection)->hasColumn('job_titles', 'wage_effective_from')) {
                Schema::connection($connection)->table('job_titles', function (Blueprint $table) {
                    $table->dropColumn('wage_effective_from');
                });
            }
        }
    }
};
