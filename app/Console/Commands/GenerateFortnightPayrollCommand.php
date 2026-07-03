<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PublicHoliday;
use App\Models\TimesheetApproval;
use App\Support\AdminPayroll;
use Illuminate\Console\Command;

class GenerateFortnightPayrollCommand extends Command
{
    protected $signature = 'payroll:generate-fortnights
                            {--finalize : Finalize runs and update leave balances}
                            {--fortnight= : Fortnight start date (YYYY-MM-DD), defaults to previous completed fortnight}';

    protected $description = 'Generate payroll runs for all tenant organizations (end-of-fortnight automation)';

    public function handle(): int
    {
        $finalize = (bool) $this->option('finalize');
        $fortnightOption = $this->option('fortnight');

        $fortnightStart = is_string($fortnightOption) && $fortnightOption !== ''
            ? AdminPayroll::normalizeFortnightStart($fortnightOption)
            : AdminPayroll::normalizeFortnightStart(
                now(config('app.display_timezone', 'Australia/Sydney'))->subDays(14)->toDateString()
            );

        $fortnightEnd = AdminPayroll::fortnightEndForStart($fortnightStart);
        $generated = 0;

        $companies = Company::query()->whereNotNull('tenant_connection')->get();

        foreach ($companies as $company) {
            $conn = $company->tenant_connection;
            if ($conn === null || $conn === '') {
                continue;
            }

            $existing = PayrollRun::on($conn)
                ->where('fortnight_start', $fortnightStart)
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->exists();

            if ($existing) {
                $this->line("Skip {$company->slug}: fortnight {$fortnightStart} already finalized.");

                continue;
            }

            $employees = $this->loadEmployees($conn, $fortnightStart, $fortnightEnd);
            $previewRows = AdminPayroll::previewFortnight(
                $conn,
                $employees,
                $fortnightStart,
                $fortnightEnd,
                PublicHoliday::on($conn)->whereBetween('holiday_date', [$fortnightStart, $fortnightEnd])->get(),
                TimesheetApproval::on($conn)->get(),
            );

            if (AdminPayroll::payableRows($previewRows) === []) {
                $this->line("Skip {$company->slug}: no payable rows for {$fortnightStart}.");

                continue;
            }

            AdminPayroll::persistRun(
                $conn,
                $fortnightStart,
                $fortnightEnd,
                $previewRows,
                'system:payroll:generate-fortnights',
                $finalize,
            );

            $generated++;
            $this->info("Generated payroll for {$company->slug} ({$fortnightStart} – {$fortnightEnd}).");
        }

        $this->info("Done. {$generated} organization(s) processed.");

        return self::SUCCESS;
    }

    private function loadEmployees(string $conn, string $fortnightStart, string $fortnightEnd): \Illuminate\Support\Collection
    {
        $tz = config('app.display_timezone', 'Australia/Sydney');
        $entriesFrom = \Carbon\Carbon::parse($fortnightStart, $tz)->startOfDay()->utc()->subDay();
        $entriesTo = \Carbon\Carbon::parse($fortnightEnd, $tz)->endOfDay()->utc()->addDay();

        return Employee::on($conn)
            ->where('employment_status', 'active')
            ->with([
                'timeClockEntries' => static function ($query) use ($entriesFrom, $entriesTo): void {
                    $query->whereBetween('clocked_at', [$entriesFrom, $entriesTo])->orderBy('clocked_at');
                },
                'scheduleShifts' => static function ($query) use ($fortnightStart, $fortnightEnd): void {
                    $query->whereBetween('scheduled_date', [$fortnightStart, $fortnightEnd]);
                },
                'leaveRecords' => static function ($query) use ($fortnightStart, $fortnightEnd): void {
                    $query->where('status', 'pending')
                        ->whereBetween('leave_date', [$fortnightStart, $fortnightEnd]);
                },
            ])
            ->get();
    }
}
