<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * In-app study pages replace file-based training_materials.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('training_pages')) {
                Schema::connection($connection)->create('training_pages', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
                    $table->string('title', 200);
                    $table->text('body');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['training_module_id', 'sort_order']);
                });
            }

            if (Schema::connection($connection)->hasTable('training_materials')) {
                Schema::connection($connection)->dropIfExists('training_materials');
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('training_pages');

            if (! Schema::connection($connection)->hasTable('training_materials')) {
                Schema::connection($connection)->create('training_materials', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
                    $table->string('title', 200);
                    $table->string('file_path', 500);
                    $table->string('original_filename', 255)->nullable();
                    $table->string('mime_type', 120)->nullable();
                    $table->string('kind', 20);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();
                    $table->index(['training_module_id', 'sort_order']);
                });
            }
        }
    }
};
