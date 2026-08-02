<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Organization-level leave type catalogue per tenant (tenant DB).
 *
 * Defines the leave types an organization offers (paid/unpaid, entitlement,
 * approval). Employee leave records reference a type by its `code`.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('leave_types')) {
                continue;
            }

            Schema::connection($connection)->create('leave_types', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('code', 32)->unique();
                $table->boolean('is_paid')->default(true);
                $table->decimal('default_annual_hours', 8, 2)->nullable();
                $table->boolean('requires_approval')->default(true);
                $table->boolean('is_active')->default(true);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->string('notes', 500)->nullable();
                $table->string('created_by', 200)->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order']);
            });

            // Seed the leave types the existing payroll/leave logic already relies on,
            // so historical EmployeeLeaveRecord rows keep mapping to a defined type.
            $now = now();
            DB::connection($connection)->table('leave_types')->insert([
                [
                    'name' => 'Annual leave',
                    'code' => 'annual',
                    'is_paid' => true,
                    'default_annual_hours' => 152.00,
                    'requires_approval' => true,
                    'is_active' => true,
                    'sort_order' => 1,
                    'notes' => null,
                    'created_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Sick leave',
                    'code' => 'sick',
                    'is_paid' => true,
                    'default_annual_hours' => 76.00,
                    'requires_approval' => false,
                    'is_active' => true,
                    'sort_order' => 2,
                    'notes' => null,
                    'created_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'Unpaid leave',
                    'code' => 'unpaid',
                    'is_paid' => false,
                    'default_annual_hours' => null,
                    'requires_approval' => true,
                    'is_active' => true,
                    'sort_order' => 3,
                    'notes' => null,
                    'created_by' => 'system',
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('leave_types');
        }
    }
};
