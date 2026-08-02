<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('shifts')) {
                continue;
            }
            if (Schema::connection($connection)->hasColumn('shifts', 'breaks')) {
                continue;
            }

            Schema::connection($connection)->table('shifts', function (Blueprint $table) {
                $table->json('breaks')->nullable()->after('breaks_summary');
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('shifts')) {
                continue;
            }
            if (! Schema::connection($connection)->hasColumn('shifts', 'breaks')) {
                continue;
            }

            Schema::connection($connection)->table('shifts', function (Blueprint $table) {
                $table->dropColumn('breaks');
            });
        }
    }
};
