<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mandatory induction for newly approved employees.
 * Existing employees stay "not_required" so current rosters are not locked out.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('employees')
                && ! Schema::connection($connection)->hasColumn('employees', 'induction_status')
            ) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->string('induction_status', 20)->default('not_required')->after('employment_status');
                    $table->timestamp('induction_passed_at')->nullable()->after('induction_status');
                    $table->timestamp('induction_overridden_at')->nullable()->after('induction_passed_at');
                    $table->string('induction_overridden_by', 200)->nullable()->after('induction_overridden_at');
                    $table->text('induction_override_reason')->nullable()->after('induction_overridden_by');
                    $table->index('induction_status');
                });
            }

            if (
                Schema::connection($connection)->hasTable('training_modules')
                && ! Schema::connection($connection)->hasColumn('training_modules', 'is_induction')
            ) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) {
                    $table->boolean('is_induction')->default(false)->after('status');
                    $table->unsignedTinyInteger('max_attempts')->default(1)->after('pass_percent');
                });
            }

            if (
                Schema::connection($connection)->hasTable('training_assignments')
                && ! Schema::connection($connection)->hasTable('induction_attempts')
            ) {
                Schema::connection($connection)->create('induction_attempts', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->foreignId('training_assignment_id')->constrained('training_assignments')->cascadeOnDelete();
                    $table->foreignId('training_module_id')->nullable()->constrained('training_modules')->nullOnDelete();
                    $table->unsignedTinyInteger('attempt_number');
                    $table->unsignedInteger('score')->nullable();
                    $table->unsignedInteger('max_score')->nullable();
                    $table->decimal('percent', 5, 2)->nullable();
                    $table->boolean('passed')->default(false);
                    $table->timestamp('submitted_at')->nullable();
                    $table->timestamps();

                    $table->unique(['training_assignment_id', 'attempt_number'], 'induction_attempt_number_unique');
                    $table->index(['employee_id', 'submitted_at']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('induction_attempts');

            if (
                Schema::connection($connection)->hasTable('training_modules')
                && Schema::connection($connection)->hasColumn('training_modules', 'is_induction')
            ) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) {
                    $table->dropColumn(['is_induction', 'max_attempts']);
                });
            }

            if (
                Schema::connection($connection)->hasTable('employees')
                && Schema::connection($connection)->hasColumn('employees', 'induction_status')
            ) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) {
                    $table->dropIndex(['induction_status']);
                    $table->dropColumn([
                        'induction_status',
                        'induction_passed_at',
                        'induction_overridden_at',
                        'induction_overridden_by',
                        'induction_override_reason',
                    ]);
                });
            }
        }
    }
};
