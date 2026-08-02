<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant job title catalog and employee FK (workforce setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('job_titles')) {
                Schema::connection($connection)->create('job_titles', function (Blueprint $table) {
                    $table->id();
                    $table->string('name', 160);
                    $table->boolean('is_active')->default(true)->index();
                    $table->timestamps();
                });
            }

            if (Schema::connection($connection)->hasTable('employees')
                && ! Schema::connection($connection)->hasColumn('employees', 'job_title_id')) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->foreignId('job_title_id')->nullable()->after('job_title')->constrained('job_titles')->nullOnDelete();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employees')
                && Schema::connection($connection)->hasColumn('employees', 'job_title_id')) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->dropForeign(['job_title_id']);
                    $table->dropColumn('job_title_id');
                });
            }

            Schema::connection($connection)->dropIfExists('job_titles');
        }
    }
};
