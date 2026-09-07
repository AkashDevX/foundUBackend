<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single hourly wage per job title (FoundU-style one wage per position).
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
                if (! Schema::connection($connection)->hasColumn('job_titles', 'hourly_wage')) {
                    $table->decimal('hourly_wage', 10, 2)->nullable()->after('color');
                }
                if (! Schema::connection($connection)->hasColumn('job_titles', 'rate_kind')) {
                    $table->string('rate_kind', 64)->nullable()->after('hourly_wage');
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
                if (Schema::connection($connection)->hasColumn('job_titles', 'rate_kind')) {
                    $table->dropColumn('rate_kind');
                }
                if (Schema::connection($connection)->hasColumn('job_titles', 'hourly_wage')) {
                    $table->dropColumn('hourly_wage');
                }
            });
        }
    }
};
