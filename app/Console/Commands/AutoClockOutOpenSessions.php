<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\TimeClockEntry;
use App\Services\TimeClockService;
use App\Support\AdminWeeklySchedule;
use App\Support\AutoClockOut;
use App\Support\ClockInGrace;
use App\Support\DisplayTimezone;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Server-side safety net that clocks employees out even when their phone is off,
 * restarted, or the app was swiped away (the on-device geofence monitor can only
 * run while the app process is alive). Closes each open session at its scheduled
 * shift end (+ grace), falling back to a hard max-session-hours cap.
 */
class AutoClockOutOpenSessions extends Command
{
    protected $signature = 'time-clock:auto-clock-out {--dry-run : Report what would be closed without writing}';

    protected $description = 'Automatically clock out employees whose shift has ended (works when the app is closed).';

    public function handle(TimeClockService $service): int
    {
        if (! (bool) config('time_clock.auto_clock_out.enabled', true)) {
            $this->info('Auto clock-out is disabled (time_clock.auto_clock_out.enabled=false).');

            return self::SUCCESS;
        }

        $originalConnection = DB::getDefaultConnection();
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        $companies = Company::query()
            ->tenantOrganizations()
            ->where('is_active', true)
            ->whereNotNull('tenant_connection')
            ->get();

        foreach ($companies as $company) {
            if (! $company->hasTenantDatabase()) {
                continue;
            }

            try {
                DB::setDefaultConnection($company->tenant_connection);
                $total += $this->processTenant($company, $service, $dryRun);
            } catch (Throwable $e) {
                $this->error("[{$company->slug}] {$e->getMessage()}");
            } finally {
                DB::setDefaultConnection($originalConnection);
            }
        }

        $verb = $dryRun ? 'would close' : 'closed';
        $this->info("Auto clock-out complete. {$verb} {$total} open session(s).");

        return self::SUCCESS;
    }

    private function processTenant(Company $company, TimeClockService $service, bool $dryRun): int
    {
        $now = DisplayTimezone::now();
        $tz = DisplayTimezone::name();
        $grace = max(0, (int) config('time_clock.auto_clock_out.shift_end_grace_minutes', 10));
        $maxHours = max(1, (int) config('time_clock.auto_clock_out.max_session_hours', 16));

        // Latest entry per employee that still belongs to an open session
        // (clock_in, or break_start / break_end after clock-in with no clock-out yet).
        $openEntries = TimeClockEntry::query()
            ->whereIn('id', static function ($query): void {
                $query->from('time_clock_entries')
                    ->selectRaw('MAX(id)')
                    ->groupBy('employee_id');
            })
            ->whereIn('event_type', TimeClockEntry::ON_SHIFT_EVENTS)
            ->with('employee')
            ->get();

        $closed = 0;

        foreach ($openEntries as $entry) {
            $employee = $entry->employee;
            if (! $employee instanceof Employee || $entry->clocked_at === null) {
                continue;
            }

            // Use the session clock-in for shift-end / max-hours decisions.
            $clockIn = TimeClockEntry::query()
                ->where('employee_id', $employee->id)
                ->where('event_type', TimeClockEntry::EVENT_CLOCK_IN)
                ->where('clocked_at', '<=', $entry->clocked_at)
                ->where(static function ($query) use ($entry): void {
                    $query->where('clocked_at', '<', $entry->clocked_at)
                        ->orWhere(static function ($inner) use ($entry): void {
                            $inner->where('clocked_at', $entry->clocked_at)
                                ->where('id', '<=', $entry->id);
                        });
                })
                ->orderByDesc('clocked_at')
                ->orderByDesc('id')
                ->first();

            $clockInAt = $clockIn instanceof TimeClockEntry && $clockIn->clocked_at !== null
                ? $clockIn->clocked_at->copy()
                : $entry->clocked_at;

            // An admin put this shift back in progress so the employee can
            // clock out from the app. Do not immediately close it again at
            // shift end or from a stale "left site" sample. The max-hours
            // cap still applies, measured from when it was reopened.
            $reopenedAt = $clockIn instanceof TimeClockEntry ? $clockIn->reopened_at : null;
            if ($reopenedAt !== null) {
                [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
                    null,
                    $reopenedAt,
                    $now,
                    $tz,
                    $grace,
                    $maxHours,
                );
                if ($closeAt === null) {
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '[%s] would clock out employee #%d (%s) at %s.',
                        $company->slug,
                        $employee->id,
                        $reason,
                        DisplayTimezone::formatDateTime($closeAt),
                    ));
                    $closed++;

                    continue;
                }

                $result = $service->systemClockOut(
                    $employee,
                    $closeAt,
                    TimeClockEntry::PUNCH_SOURCE_AUTO_SHIFT_END,
                    'Auto clock-out: '.$reason,
                );
                if ($result !== null) {
                    $closed++;
                    $this->line(sprintf(
                        '[%s] clocked out employee #%d (%s).',
                        $company->slug,
                        $employee->id,
                        $reason,
                    ));
                }

                continue;
            }

            $leftSite = $service->openSessionLeftSite($employee);
            if ($leftSite !== null) {
                if ($dryRun) {
                    $this->line(sprintf(
                        '[%s] would clock out employee #%d (left work site) at %s.',
                        $company->slug,
                        $employee->id,
                        DisplayTimezone::formatDateTime($leftSite['at']),
                    ));
                    $closed++;

                    continue;
                }

                $result = $service->systemClockOut(
                    $employee,
                    $leftSite['at'],
                    TimeClockEntry::PUNCH_SOURCE_AUTO_GEOFENCE_EXIT,
                    'Auto clock-out: left work site',
                    $leftSite['device'],
                );
                if ($result !== null) {
                    $closed++;
                    $this->line(sprintf(
                        '[%s] clocked out employee #%d (left work site).',
                        $company->slug,
                        $employee->id,
                    ));
                }

                continue;
            }

            $times = $this->shiftTimesForClockIn($employee, $clockIn, $clockInAt, $tz);

            [$closeAt, $reason] = AutoClockOut::resolveCloseAt(
                $times,
                $clockInAt,
                $now,
                $tz,
                $grace,
                $maxHours,
            );
            if ($closeAt === null) {
                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '[%s] would clock out employee #%d (%s) at %s.',
                    $company->slug,
                    $employee->id,
                    $reason,
                    DisplayTimezone::formatDateTime($closeAt),
                ));
                $closed++;

                continue;
            }

            $result = $service->systemClockOut(
                $employee,
                $closeAt,
                TimeClockEntry::PUNCH_SOURCE_AUTO_SHIFT_END,
                'Auto clock-out: '.$reason,
            );

            if ($result !== null) {
                $closed++;
                $this->line(sprintf(
                    '[%s] clocked out employee #%d (%s).',
                    $company->slug,
                    $employee->id,
                    $reason,
                ));
            }
        }

        return $closed;
    }

    /**
     * The roster row linked to the punch wins. Falling back to "whichever shift
     * contains the clock-in time" can pick a later shift and leave the morning
     * session open for hours after the employee has left.
     *
     * @return array{start_time: string, end_time: string}|null
     */
    private function shiftTimesForClockIn(Employee $employee, ?TimeClockEntry $clockIn, CarbonInterface $clockInAt, string $tz): ?array
    {
        $shiftId = $clockIn?->schedule_shift_id;
        if ($shiftId !== null && (int) $shiftId > 0) {
            $shift = EmployeeScheduleShift::query()->find((int) $shiftId);
            if ($shift instanceof EmployeeScheduleShift) {
                $start = ClockInGrace::storedTimeToHm($shift->start_time, '');
                $end = ClockInGrace::storedTimeToHm($shift->end_time, '');
                if ($start !== '' && $end !== '') {
                    return [
                        'start_time' => $start,
                        'end_time' => $end,
                    ];
                }
            }
        }

        return AdminWeeklySchedule::shiftTimesForDate(
            $employee,
            $clockInAt->copy()->timezone($tz),
        );
    }
}
