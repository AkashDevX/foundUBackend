<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * MySQL TIMESTAMP applies session timezone conversion; store clock punches as plain UTC datetime.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            DB::connection($connection)->statement(
                'ALTER TABLE time_clock_entries MODIFY clocked_at DATETIME NOT NULL'
            );
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            DB::connection($connection)->statement(
                'ALTER TABLE time_clock_entries MODIFY clocked_at TIMESTAMP NOT NULL'
            );
        }
    }
};
