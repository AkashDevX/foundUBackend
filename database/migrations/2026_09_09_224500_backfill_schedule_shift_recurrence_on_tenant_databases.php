<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Backfill recurrence metadata for existing multi-date shift series (tenant DBs).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (config('tenants.tenant_migration_connections', []) as $connection) {
            if (! Schema::connection($connection)->hasTable('employee_schedule_shifts')) {
                continue;
            }
            if (! Schema::connection($connection)->hasColumn('employee_schedule_shifts', 'recurrence_mode')) {
                continue;
            }

            $rows = DB::connection($connection)
                ->table('employee_schedule_shifts')
                ->where('entry_type', 'shift')
                ->whereNull('recurrence_mode')
                ->orderBy('employee_id')
                ->orderBy('scheduled_date')
                ->get([
                    'id',
                    'employee_id',
                    'scheduled_date',
                    'start_time',
                    'end_time',
                    'shift_id',
                    'work_location_id',
                    'job_title_id',
                ]);

            $groups = [];
            foreach ($rows as $row) {
                $start = is_string($row->start_time) ? substr($row->start_time, 0, 5) : null;
                $end = is_string($row->end_time) ? substr($row->end_time, 0, 5) : null;
                $key = implode('|', [
                    (string) $row->employee_id,
                    (string) $start,
                    (string) $end,
                    (string) ($row->shift_id ?? 0),
                    (string) ($row->work_location_id ?? 0),
                    (string) ($row->job_title_id ?? 0),
                ]);
                $groups[$key][] = $row;
            }

            $dayMap = [0 => 'sun', 1 => 'mon', 2 => 'tue', 3 => 'wed', 4 => 'thu', 5 => 'fri', 6 => 'sat'];

            foreach ($groups as $group) {
                if (count($group) < 2) {
                    continue;
                }

                $dates = [];
                $days = [];
                foreach ($group as $row) {
                    $date = substr((string) $row->scheduled_date, 0, 10);
                    $dates[] = $date;
                    $dow = (int) date('w', strtotime($date.' UTC'));
                    $days[$dayMap[$dow] ?? 'mon'] = true;
                }
                sort($dates);

                $mode = $this->inferMode($dates);
                $seriesId = (string) Str::uuid();
                $dayList = array_keys($days);
                sort($dayList);

                DB::connection($connection)
                    ->table('employee_schedule_shifts')
                    ->whereIn('id', array_map(static fn ($row) => $row->id, $group))
                    ->update([
                        'recurrence_series_id' => $seriesId,
                        'recurrence_mode' => $mode,
                        'recurrence_starts' => $dates[0],
                        'recurrence_until' => $dates[array_key_last($dates)],
                        'recurrence_days' => json_encode(array_values($dayList)),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    /**
     * @param  list<string>  $dates
     */
    private function inferMode(array $dates): string
    {
        if (count($dates) < 2) {
            return 'every_week';
        }

        $gaps = [];
        for ($i = 1; $i < count($dates); $i++) {
            $prev = new DateTimeImmutable($dates[$i - 1]);
            $curr = new DateTimeImmutable($dates[$i]);
            $gaps[] = (int) $prev->diff($curr)->days;
        }

        $avg = array_sum($gaps) / max(1, count($gaps));
        if ($avg >= 52) {
            return 'every_8_weeks';
        }
        if ($avg >= 45) {
            return 'every_7_weeks';
        }
        if ($avg >= 38) {
            return 'every_6_weeks';
        }
        if ($avg >= 31) {
            return 'every_5_weeks';
        }
        if ($avg >= 24) {
            return 'every_4_weeks';
        }
        if ($avg >= 17) {
            return 'every_3_weeks';
        }
        if ($avg >= 10) {
            return 'every_2_weeks';
        }

        return 'every_week';
    }

    public function down(): void
    {
        // Keep backfilled recurrence metadata.
    }
};
