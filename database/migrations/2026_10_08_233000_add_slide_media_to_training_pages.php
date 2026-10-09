<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Slide pictures and bullet points for study pages, plus a picture inside each toggle.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_pages')
                && ! Schema::connection($connection)->hasColumn('training_pages', 'image_path')
            ) {
                Schema::connection($connection)->table('training_pages', function (Blueprint $table) {
                    $table->string('image_path', 500)->nullable()->after('body');
                    $table->json('bullets')->nullable()->after('image_path');
                });
            }

            if (
                Schema::connection($connection)->hasTable('training_page_sections')
                && ! Schema::connection($connection)->hasColumn('training_page_sections', 'image_path')
            ) {
                Schema::connection($connection)->table('training_page_sections', function (Blueprint $table) {
                    $table->string('image_path', 500)->nullable()->after('body');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_pages')
                && Schema::connection($connection)->hasColumn('training_pages', 'image_path')
            ) {
                Schema::connection($connection)->table('training_pages', function (Blueprint $table) {
                    $table->dropColumn(['image_path', 'bullets']);
                });
            }

            if (
                Schema::connection($connection)->hasTable('training_page_sections')
                && Schema::connection($connection)->hasColumn('training_page_sections', 'image_path')
            ) {
                Schema::connection($connection)->table('training_page_sections', function (Blueprint $table) {
                    $table->dropColumn('image_path');
                });
            }
        }
    }
};
