<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expandable subtopics under each study page (admin-authored, toggled in the app).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('training_page_sections')) {
                Schema::connection($connection)->create('training_page_sections', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_page_id')->constrained('training_pages')->cascadeOnDelete();
                    $table->string('title', 200);
                    $table->text('body');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['training_page_id', 'sort_order']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('training_page_sections');
        }
    }
};
