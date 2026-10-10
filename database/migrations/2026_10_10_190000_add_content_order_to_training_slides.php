<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Remembers where each picture sits among the title, text, and other slide content.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            foreach (['training_pages', 'training_page_sections'] as $table) {
                if (
                    Schema::connection($connection)->hasTable($table)
                    && ! Schema::connection($connection)->hasColumn($table, 'content_order')
                ) {
                    Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                        $blueprint->json('content_order')->nullable();
                    });
                }
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            foreach (['training_pages', 'training_page_sections'] as $table) {
                if (
                    Schema::connection($connection)->hasTable($table)
                    && Schema::connection($connection)->hasColumn($table, 'content_order')
                ) {
                    Schema::connection($connection)->table($table, function (Blueprint $blueprint): void {
                        $blueprint->dropColumn('content_order');
                    });
                }
            }
        }
    }
};
