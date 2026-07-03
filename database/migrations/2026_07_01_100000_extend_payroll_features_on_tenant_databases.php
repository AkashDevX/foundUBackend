<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-employee payroll rates, leave dollar balances, leave records, roster flags.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->table('employees', function (Blueprint $table) use ($connection) {
                if (! Schema::connection($connection)->hasColumn('employees', 'payroll_rates_json')) {
                    $table->json('payroll_rates_json')->nullable()->after('award_level');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'is_non_rotating_shift')) {
                    $table->boolean('is_non_rotating_shift')->default(false)->after('payroll_rates_json');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'sick_leave_balance_amount')) {
                    $table->decimal('sick_leave_balance_amount', 12, 2)->default(0)->after('sick_leave_balance_hours');
                }
                if (! Schema::connection($connection)->hasColumn('employees', 'annual_leave_balance_amount')) {
                    $table->decimal('annual_leave_balance_amount', 12, 2)->default(0)->after('annual_leave_balance_hours');
                }
            });

            if (! Schema::connection($connection)->hasTable('employee_leave_records')) {
                Schema::connection($connection)->create('employee_leave_records', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->string('leave_type', 32);
                    $table->date('leave_date')->index();
                    $table->decimal('hours', 8, 2);
                    $table->decimal('hourly_rate', 10, 2)->nullable();
                    $table->decimal('loading_percent', 5, 2)->nullable();
                    $table->decimal('paid_amount', 12, 2)->default(0);
                    $table->string('status', 32)->default('pending')->index();
                    $table->foreignId('payroll_run_id')->nullable()->constrained('payroll_runs')->nullOnDelete();
                    $table->string('notes', 500)->nullable();
                    $table->string('created_by', 200)->nullable();
                    $table->timestamps();

                    $table->index(['employee_id', 'leave_date', 'status']);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_leave_records');

            Schema::connection($connection)->table('employees', function (Blueprint $table) use ($connection) {
                foreach (['annual_leave_balance_amount', 'sick_leave_balance_amount', 'is_non_rotating_shift', 'payroll_rates_json'] as $col) {
                    if (Schema::connection($connection)->hasColumn('employees', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
