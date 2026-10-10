<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks a clock-in whose accidental clock-out was undone by an admin.
 * Automatic shift-end clock-out waits until the employee punches out,
 * or until the normal max-session cap measured from this time.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('time_clock_entries', 'reopened_at')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->dateTime('reopened_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('time_clock_entries', 'reopened_at')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->dropColumn('reopened_at');
            });
        }
    }
};
