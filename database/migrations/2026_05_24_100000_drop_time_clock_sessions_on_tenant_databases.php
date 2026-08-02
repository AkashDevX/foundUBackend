<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * Keep event-based time_clock_entries (used by the API). Drop legacy time_clock_sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('time_clock_sessions');
        }
    }

    public function down(): void
    {
        // Legacy table was removed intentionally; do not recreate.
    }
};
