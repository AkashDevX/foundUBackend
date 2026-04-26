<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant reference data (departments, work locations, shifts) and employee assignment FKs.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('departments')) {
                Schema::connection($connection)->create('departments', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->string('code', 32)->nullable()->index();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('work_locations')) {
                Schema::connection($connection)->create('work_locations', function (Blueprint $table) {
                $table->id();
                $table->string('name', 200);
                $table->text('address')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                });
            }

            if (! Schema::connection($connection)->hasTable('shifts')) {
                Schema::connection($connection)->create('shifts', function (Blueprint $table) {
                $table->id();
                $table->string('name', 160);
                $table->time('start_time');
                $table->time('end_time');
                $table->json('shift_days')->nullable();
                $table->string('breaks_summary', 255)->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                });
            }

            if (Schema::connection($connection)->hasTable('employees')) {
                Schema::connection($connection)->table('employees', function (Blueprint $table) use ($connection) {
                    if (! Schema::connection($connection)->hasColumn('employees', 'department_id')) {
                        $table->foreignId('department_id')->nullable()->after('department')->constrained('departments')->nullOnDelete();
                    }
                    if (! Schema::connection($connection)->hasColumn('employees', 'work_location_id')) {
                        $table->foreignId('work_location_id')->nullable()->after('department_id')->constrained('work_locations')->nullOnDelete();
                    }
                    if (! Schema::connection($connection)->hasColumn('employees', 'shift_id')) {
                        $table->foreignId('shift_id')->nullable()->after('work_location_id')->constrained('shifts')->nullOnDelete();
                    }
                    if (! Schema::connection($connection)->hasColumn('employees', 'assignment_effective_from')) {
                        $table->date('assignment_effective_from')->nullable()->after('shift_id');
                    }
                    if (! Schema::connection($connection)->hasColumn('employees', 'assignment_notes')) {
                        $table->text('assignment_notes')->nullable()->after('assignment_effective_from');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->table('employees', function (Blueprint $table) {
                $table->dropForeign(['department_id']);
                $table->dropForeign(['work_location_id']);
                $table->dropForeign(['shift_id']);
                $table->dropColumn([
                    'department_id',
                    'work_location_id',
                    'shift_id',
                    'assignment_effective_from',
                    'assignment_notes',
                ]);
            });

            Schema::connection($connection)->dropIfExists('shifts');
            Schema::connection($connection)->dropIfExists('work_locations');
            Schema::connection($connection)->dropIfExists('departments');
        }
    }
};
