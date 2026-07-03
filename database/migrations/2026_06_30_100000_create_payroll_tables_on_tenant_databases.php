<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll award rates, pay runs, public holidays, and employee payroll fields (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('payroll_award_rates')) {
                Schema::connection($connection)->create('payroll_award_rates', function (Blueprint $table) {
                    $table->id();
                    $table->string('employment_type', 32);
                    $table->string('award_level', 32);
                    $table->string('rate_type', 64);
                    $table->decimal('amount', 10, 2);
                    $table->date('effective_from');
                    $table->timestamps();

                    $table->unique(
                        ['employment_type', 'award_level', 'rate_type', 'effective_from'],
                        'payroll_rates_unique'
                    );
                    $table->index(['employment_type', 'award_level', 'effective_from'], 'payroll_rates_lookup_idx');
                });
            }

            if (! Schema::connection($connection)->hasTable('public_holidays')) {
                Schema::connection($connection)->create('public_holidays', function (Blueprint $table) {
                    $table->id();
                    $table->date('holiday_date')->unique();
                    $table->string('name', 160);
                    $table->string('region', 32)->nullable();
                    $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('payroll_runs')) {
                Schema::connection($connection)->create('payroll_runs', function (Blueprint $table) {
                    $table->id();
                    $table->date('fortnight_start')->index();
                    $table->date('fortnight_end');
                    $table->string('status', 32)->default('draft')->index();
                    $table->timestamp('generated_at')->nullable();
                    $table->string('generated_by', 200)->nullable();
                    $table->text('notes')->nullable();
                    $table->timestamps();

                    $table->unique(['fortnight_start']);
                });
            }

            if (! Schema::connection($connection)->hasTable('payroll_run_lines')) {
                Schema::connection($connection)->create('payroll_run_lines', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('payroll_run_id')->constrained('payroll_runs')->cascadeOnDelete();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->string('rate_type', 64);
                    $table->string('description', 255);
                    $table->decimal('hours', 10, 2)->default(0);
                    $table->decimal('rate', 10, 2)->default(0);
                    $table->decimal('amount', 12, 2)->default(0);
                    $table->unsignedSmallInteger('sort_order')->default(0);
                    $table->timestamps();

                    $table->index(['payroll_run_id', 'employee_id'], 'payroll_run_lines_run_emp_idx');
                });
            }

            Schema::connection($connection)->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'employment_type')) {
                    $table->string('employment_type', 32)->nullable()->after('employment_status');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'award_level')) {
                    $table->string('award_level', 32)->nullable()->after('employment_type');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'payroll_allowances_json')) {
                    $table->json('payroll_allowances_json')->nullable()->after('bank_name');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'sick_leave_balance_hours')) {
                    $table->decimal('sick_leave_balance_hours', 10, 2)->default(0)->after('payroll_allowances_json');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'annual_leave_balance_hours')) {
                    $table->decimal('annual_leave_balance_hours', 10, 2)->default(0)->after('sick_leave_balance_hours');
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('payroll_run_lines');
            Schema::connection($connection)->dropIfExists('payroll_runs');
            Schema::connection($connection)->dropIfExists('public_holidays');
            Schema::connection($connection)->dropIfExists('payroll_award_rates');

            Schema::connection($connection)->table('employees', function (Blueprint $table) use ($connection) {
                foreach (['annual_leave_balance_hours', 'sick_leave_balance_hours', 'payroll_allowances_json', 'award_level', 'employment_type'] as $col) {
                    if (Schema::connection($connection)->hasColumn('employees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
