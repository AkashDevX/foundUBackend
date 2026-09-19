<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Many job titles per employee; primary stays mirrored on employees.job_title_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employees')
                || ! Schema::connection($connection)->hasTable('job_titles')) {
                continue;
            }

            if (! Schema::connection($connection)->hasTable('employee_job_title')) {
                Schema::connection($connection)->create('employee_job_title', function (Blueprint $table) {
                    $table->id();
                    $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
                    $table->foreignId('job_title_id')->constrained('job_titles')->cascadeOnDelete();
                    $table->boolean('is_primary')->default(false);
                    $table->timestamps();
                    $table->unique(['employee_id', 'job_title_id']);
                });
            }

            if (! Schema::connection($connection)->hasColumn('employees', 'job_title_id')) {
                continue;
            }

            $rows = DB::connection($connection)
                ->table('employees')
                ->whereNotNull('job_title_id')
                ->select(['id', 'job_title_id'])
                ->get();

            $now = now();
            foreach ($rows as $row) {
                $exists = DB::connection($connection)
                    ->table('employee_job_title')
                    ->where('employee_id', $row->id)
                    ->where('job_title_id', $row->job_title_id)
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::connection($connection)->table('employee_job_title')->insert([
                    'employee_id' => $row->id,
                    'job_title_id' => $row->job_title_id,
                    'is_primary' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            Schema::connection($connection)->dropIfExists('employee_job_title');
        }
    }
};
