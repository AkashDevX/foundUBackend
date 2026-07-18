<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('time_clock_entries', 'comment')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->text('comment')->nullable()->after('shift_id');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('time_clock_entries', 'comment')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->dropColumn('comment');
            });
        }
    }
};
