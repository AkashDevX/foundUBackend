<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a punch to the weekly-schedule row so two shifts on the same day
 * can be finished and located independently.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (Schema::connection($connection)->hasColumn('time_clock_entries', 'schedule_shift_id')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->unsignedBigInteger('schedule_shift_id')->nullable()->after('shift_id')->index();
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_entries')) {
                continue;
            }

            if (! Schema::connection($connection)->hasColumn('time_clock_entries', 'schedule_shift_id')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_entries', function (Blueprint $table) {
                $table->dropIndex(['schedule_shift_id']);
                $table->dropColumn('schedule_shift_id');
            });
        }
    }
};
