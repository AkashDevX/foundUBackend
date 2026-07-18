<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple default shifts per employee work assignment (tenant DB).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (Schema::connection($connection)->hasTable('employee_assignment_shifts')) {
                continue;
            }

            Schema::connection($connection)->create('employee_assignment_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('shifts')->cascadeOnDelete();
                $table->unsignedSmallInteger('unpaid_break_minutes');
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();

                $table->index(['employee_id', 'sort_order']);
            });

            if (Schema::connection($connection)->hasColumn('employees', 'shift_id')) {
                $rows = DB::connection($connection)
                    ->table('employees')
                    ->whereNotNull('shift_id')
                    ->orderBy('id')
                    ->get(['id', 'shift_id']);

                foreach ($rows as $row) {
                    DB::connection($connection)->table('employee_assignment_shifts')->insert([
                        'employee_id' => $row->id,
                        'shift_id' => $row->shift_id,
                        'unpaid_break_minutes' => 0,
                        'sort_order' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_assignment_shifts');
        }
    }
};
