<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Optional certificate of completion for training modules that require one.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (
                Schema::connection($connection)->hasTable('training_modules')
                && ! Schema::connection($connection)->hasColumn('training_modules', 'issues_certificate')
            ) {
                $afterTime = Schema::connection($connection)->hasColumn('training_modules', 'question_time_seconds');
                Schema::connection($connection)->table('training_modules', function (Blueprint $table) use ($afterTime): void {
                    $issues = $table->boolean('issues_certificate')->default(false);
                    if ($afterTime) {
                        $issues->after('question_time_seconds');
                    }
                    $table->unsignedSmallInteger('certificate_validity_months')->nullable()->after('issues_certificate');
                });
            }

            if (
                Schema::connection($connection)->hasTable('training_assignments')
                && ! Schema::connection($connection)->hasTable('training_certificates')
            ) {
                Schema::connection($connection)->create('training_certificates', function (Blueprint $table): void {
                    $table->id();
                    $table->foreignId('training_assignment_id')->constrained('training_assignments')->cascadeOnDelete();
                    $table->foreignId('training_attempt_id')->nullable()->constrained('training_attempts')->nullOnDelete();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->string('reference_number', 32);
                    $table->string('employee_name', 200);
                    $table->string('training_name', 200);
                    $table->string('company_name', 200);
                    $table->date('completed_on');
                    $table->date('expires_on')->nullable();
                    $table->timestamps();

                    $table->unique('training_assignment_id');
                    $table->unique('reference_number');
                    $table->index(['employee_id', 'completed_on']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('training_certificates');

            if (
                Schema::connection($connection)->hasTable('training_modules')
                && Schema::connection($connection)->hasColumn('training_modules', 'issues_certificate')
            ) {
                Schema::connection($connection)->table('training_modules', function (Blueprint $table): void {
                    $table->dropColumn(['issues_certificate', 'certificate_validity_months']);
                });
            }
        }
    }
};
