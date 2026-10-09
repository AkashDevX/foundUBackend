<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Organization-level meal-break window. Defaults match a break taken from the
 * 4th hour through the 6th hour, once a shift runs longer than 5 hours.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_settings')) {
                continue;
            }

            $missing = [];
            foreach ([
                'break_rule_enabled',
                'break_window_start_minutes',
                'break_window_end_minutes',
                'break_required_after_minutes',
                'break_reminder_lead_minutes',
            ] as $column) {
                if (! Schema::connection($connection)->hasColumn('time_clock_settings', $column)) {
                    $missing[] = $column;
                }
            }

            if ($missing === []) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_settings', function (Blueprint $table) use ($missing): void {
                if (in_array('break_rule_enabled', $missing, true)) {
                    $table->boolean('break_rule_enabled')->default(true);
                }
                if (in_array('break_window_start_minutes', $missing, true)) {
                    $table->unsignedSmallInteger('break_window_start_minutes')->default(240);
                }
                if (in_array('break_window_end_minutes', $missing, true)) {
                    $table->unsignedSmallInteger('break_window_end_minutes')->default(360);
                }
                if (in_array('break_required_after_minutes', $missing, true)) {
                    $table->unsignedSmallInteger('break_required_after_minutes')->default(300);
                }
                if (in_array('break_reminder_lead_minutes', $missing, true)) {
                    $table->unsignedSmallInteger('break_reminder_lead_minutes')->default(15);
                }
            });
        }
    }

    public function down(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('time_clock_settings')) {
                continue;
            }

            Schema::connection($connection)->table('time_clock_settings', function (Blueprint $table) use ($connection): void {
                foreach ([
                    'break_reminder_lead_minutes',
                    'break_required_after_minutes',
                    'break_window_end_minutes',
                    'break_window_start_minutes',
                    'break_rule_enabled',
                ] as $column) {
                    if (Schema::connection($connection)->hasColumn('time_clock_settings', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
