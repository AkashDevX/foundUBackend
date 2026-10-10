<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extra slide content: text, PDF, photos, video, links, instructions, and notes.
 * A block belongs to a slide or to one toggle under that slide.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_pages')
                && Schema::connection($connection)->hasTable('training_page_sections')
                && ! Schema::connection($connection)->hasTable('training_slide_blocks')
            ) {
                Schema::connection($connection)->create('training_slide_blocks', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('training_page_id')->nullable()->constrained('training_pages')->cascadeOnDelete();
                    $table->foreignId('training_page_section_id')->nullable()->constrained('training_page_sections')->cascadeOnDelete();
                    $table->string('kind', 32);
                    $table->string('label', 200)->nullable();
                    $table->text('body')->nullable();
                    $table->string('file_path', 500)->nullable();
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['training_page_id', 'sort_order']);
                    $table->index(['training_page_section_id', 'sort_order']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('training_slide_blocks');
        }
    }
};
