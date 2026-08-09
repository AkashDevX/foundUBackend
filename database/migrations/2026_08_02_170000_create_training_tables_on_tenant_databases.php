<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Training modules: study materials + MCQ questionnaires (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('training_modules')) {
                Schema::connection($connection)->create('training_modules', function (Blueprint $table) {
                    $table->id();
                    $table->string('title', 200);
                    $table->text('description')->nullable();
                    $table->string('status', 20)->default('draft')->index();
                    $table->unsignedTinyInteger('pass_percent')->nullable()->default(70);
                    $table->string('created_by', 200)->nullable();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('training_materials')) {
                Schema::connection($connection)->create('training_materials', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
                    $table->string('title', 200);
                    $table->string('file_path', 500);
                    $table->string('original_filename', 255)->nullable();
                    $table->string('mime_type', 120)->nullable();
                    $table->string('kind', 20); // pdf | image
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['training_module_id', 'sort_order']);
                });
            }

            if (! Schema::connection($connection)->hasTable('training_questions')) {
                Schema::connection($connection)->create('training_questions', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
                    $table->text('question_text');
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->unsignedSmallInteger('points')->default(1);
                    $table->timestamps();

                    $table->index(['training_module_id', 'sort_order']);
                });
            }

            if (! Schema::connection($connection)->hasTable('training_question_options')) {
                Schema::connection($connection)->create('training_question_options', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_question_id')->constrained('training_questions')->cascadeOnDelete();
                    $table->string('option_text', 500);
                    $table->boolean('is_correct')->default(false);
                    $table->unsignedInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['training_question_id', 'sort_order']);
                });
            }

            if (! Schema::connection($connection)->hasTable('training_assignments')) {
                Schema::connection($connection)->create('training_assignments', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_module_id')->constrained('training_modules')->cascadeOnDelete();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->string('assigned_by', 200)->nullable();
                    $table->timestamp('assigned_at')->nullable();
                    $table->date('due_date')->nullable();
                    $table->timestamps();

                    $table->unique(['training_module_id', 'employee_id']);
                    $table->index(['employee_id', 'assigned_at']);
                });
            }

            if (! Schema::connection($connection)->hasTable('training_attempts')) {
                Schema::connection($connection)->create('training_attempts', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_assignment_id')->constrained('training_assignments')->cascadeOnDelete();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->timestamp('materials_acknowledged_at')->nullable();
                    $table->json('question_order')->nullable();
                    $table->json('option_order')->nullable();
                    $table->unsignedInteger('score')->nullable();
                    $table->unsignedInteger('max_score')->nullable();
                    $table->decimal('percent', 5, 2)->nullable();
                    $table->boolean('passed')->nullable();
                    $table->timestamp('submitted_at')->nullable();
                    $table->timestamps();

                    $table->unique('training_assignment_id');
                    $table->index(['employee_id', 'submitted_at']);
                });
            }

            if (! Schema::connection($connection)->hasTable('training_attempt_answers')) {
                Schema::connection($connection)->create('training_attempt_answers', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('training_attempt_id')->constrained('training_attempts')->cascadeOnDelete();
                    $table->foreignId('training_question_id')->constrained('training_questions')->cascadeOnDelete();
                    $table->foreignId('selected_option_id')->nullable()->constrained('training_question_options')->nullOnDelete();
                    $table->boolean('is_correct')->default(false);
                    $table->timestamps();

                    $table->unique(['training_attempt_id', 'training_question_id'], 'training_attempt_question_unique');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('training_attempt_answers');
            Schema::connection($connection)->dropIfExists('training_attempts');
            Schema::connection($connection)->dropIfExists('training_assignments');
            Schema::connection($connection)->dropIfExists('training_question_options');
            Schema::connection($connection)->dropIfExists('training_questions');
            Schema::connection($connection)->dropIfExists('training_materials');
            Schema::connection($connection)->dropIfExists('training_modules');
        }
    }
};
