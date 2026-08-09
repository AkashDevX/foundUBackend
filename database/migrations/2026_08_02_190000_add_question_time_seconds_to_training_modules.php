<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-question quiz timer (seconds) on training modules.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_modules')
                && ! Schema::connection($connection)->hasColumn('training_modules', 'question_time_seconds')
            ) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) {
                    $table->unsignedSmallInteger('question_time_seconds')->default(45)->after('pass_percent');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_modules')
                && Schema::connection($connection)->hasColumn('training_modules', 'question_time_seconds')
            ) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) {
                    $table->dropColumn('question_time_seconds');
                });
            }
        }
    }
};
