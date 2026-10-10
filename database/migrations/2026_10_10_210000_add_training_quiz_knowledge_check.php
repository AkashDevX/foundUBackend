<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Knowledge-check settings: question types, feedback, retakes, and manual review.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('training_modules')) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('training_modules', 'quiz_required')) {
                        $table->boolean('quiz_required')->default(true);
                    }
                    if (! Schema::connection($connection)->hasColumn('training_modules', 'allow_retakes')) {
                        $table->boolean('allow_retakes')->default(false);
                    }
                });
            }

            if (Schema::connection($connection)->hasTable('training_questions')) {
                Schema::connection($connection)->table('training_questions', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'question_type')) {
                        $table->string('question_type', 40)->default('multiple_choice');
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'prompt')) {
                        $table->text('prompt')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'explanation')) {
                        $table->text('explanation')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'requires_review')) {
                        $table->boolean('requires_review')->default(false);
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'accepted_answers')) {
                        $table->json('accepted_answers')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'media_path')) {
                        $table->string('media_path', 500)->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_questions', 'media_kind')) {
                        $table->string('media_kind', 20)->nullable();
                    }
                });
            }

            if (Schema::connection($connection)->hasTable('training_question_options')) {
                Schema::connection($connection)->table('training_question_options', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('training_question_options', 'match_text')) {
                        $table->string('match_text', 500)->nullable();
                    }
                });
            }

            if (Schema::connection($connection)->hasTable('training_attempts')) {
                Schema::connection($connection)->table('training_attempts', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('training_attempts', 'attempt_number')) {
                        $table->unsignedInteger('attempt_number')->default(1);
                    }
                    if (! Schema::connection($connection)->hasColumn('training_attempts', 'waived')) {
                        $table->boolean('waived')->default(false);
                    }
                });

                $indexes = collect(Schema::connection($connection)->getIndexes('training_attempts'));
                $plainIndex = $indexes->contains(function (array $index): bool {
                    return ($index['unique'] ?? false) !== true
                        && ($index['primary'] ?? false) !== true
                        && ($index['columns'] ?? []) === ['training_assignment_id'];
                });
                if (! $plainIndex) {
                    Schema::connection($connection)->table('training_attempts', function (Blueprint $table): void {
                        $table->index('training_assignment_id');
                    });
                }

                $uniqueOnAssignment = $indexes->contains(function (array $index): bool {
                    return ($index['unique'] ?? false) === true
                        && ($index['columns'] ?? []) === ['training_assignment_id'];
                });
                if ($uniqueOnAssignment) {
                    Schema::connection($connection)->table('training_attempts', function (Blueprint $table): void {
                        $table->dropUnique(['training_assignment_id']);
                    });
                }
            }

            if (Schema::connection($connection)->hasTable('training_attempt_answers')) {
                Schema::connection($connection)->table('training_attempt_answers', function (Blueprint $table) use ($connection): void {
                    if (! Schema::connection($connection)->hasColumn('training_attempt_answers', 'response')) {
                        $table->json('response')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_attempt_answers', 'points_awarded')) {
                        $table->unsignedSmallInteger('points_awarded')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_attempt_answers', 'review_status')) {
                        $table->string('review_status', 20)->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_attempt_answers', 'reviewed_at')) {
                        $table->timestamp('reviewed_at')->nullable();
                    }
                    if (! Schema::connection($connection)->hasColumn('training_attempt_answers', 'reviewed_by')) {
                        $table->string('reviewed_by', 200)->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('training_attempt_answers')) {
                Schema::connection($connection)->table('training_attempt_answers', function (Blueprint $table) use ($connection): void {
                    foreach (['reviewed_by', 'reviewed_at', 'review_status', 'points_awarded', 'response'] as $column) {
                        if (Schema::connection($connection)->hasColumn('training_attempt_answers', $column)) {
                            $table->dropColumn($column);
                        }
                    }
                });
            }
        }
    }
};
