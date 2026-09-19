<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeLeaveRecord;
use App\Models\PayrollAwardRate;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use App\Models\PublicHoliday;
use App\Models\TimesheetApproval;
use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class AdminPayroll
{
    /**
     * @return list<array{start: string, end: string, label: string, has_run: bool, run_id: int|null, status: string|null}>
     */
    public static function recentFortnights(int $count = 8, ?Collection $existingRuns = null): array
    {
        $tz = DisplayTimezone::name();
        $today = Carbon::now($tz)->startOfDay();
        $currentWeekStart = $today->copy()->startOfWeek(Carbon::MONDAY);

        if ((int) $currentWeekStart->weekOfYear % 2 !== 0) {
            $currentWeekStart = $currentWeekStart->copy()->subWeek();
        }

        $runsByStart = $existingRuns !== null
            ? $existingRuns->keyBy(static fn (PayrollRun $r) => $r->fortnight_start?->toDateString() ?? '')
            : collect();

        $fortnights = [];
        $cursor = $currentWeekStart->copy();

        for ($i = 0; $i < $count; $i++) {
            $start = $cursor->copy();
            $end = $start->copy()->addDays(13);
            $startStr = $start->toDateString();
            /** @var PayrollRun|null $run */
            $run = $runsByStart->get($startStr);

            $fortnights[] = [
                'start' => $startStr,
                'end' => $end->toDateString(),
                'label' => sprintf(
                    '%s – %s',
                    DisplayTimezone::format($start, 'M j, Y'),
                    DisplayTimezone::format($end, 'M j, Y')
                ),
                'has_run' => $run !== null,
                'run_id' => $run?->id,
                'status' => $run?->status,
            ];

            $cursor = $cursor->copy()->subWeeks(2);
        }

        return $fortnights;
    }

    public static function normalizeFortnightStart(string $date): string
    {
        $tz = DisplayTimezone::name();
        $parsed = Carbon::parse($date, $tz)->startOfDay();
        $weekStart = $parsed->copy()->startOfWeek(Carbon::MONDAY);

        if ((int) $weekStart->weekOfYear % 2 !== 0) {
            $weekStart = $weekStart->copy()->subWeek();
        }

        return $weekStart->toDateString();
    }

    public static function fortnightEndForStart(string $fortnightStart): string
    {
        return Carbon::parse($fortnightStart, DisplayTimezone::name())
            ->addDays(13)
            ->toDateString();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array{
     *     employee: Employee,
     *     total_hours: float,
     *     total_amount: float,
     *     lines: list<array<string, mixed>>,
     *     sick_leave_accrued: float,
     *     annual_leave_accrued: float,
     *     skipped_reason: string|null,
     * }>
     */
    public static function previewFortnight(
        string $connection,
        Collection $employees,
        string $fortnightStart,
        string $fortnightEnd,
        Collection $publicHolidays,
        Collection $timesheetApprovals,
    ): array {
        $tz = DisplayTimezone::name();
        $rangeStart = Carbon::parse($fortnightStart, $tz)->startOfDay();
        $rangeEnd = Carbon::parse($fortnightEnd, $tz)->endOfDay();
        $requireApproved = (bool) config('payroll.require_approved_timesheets', true);

        $approvalKeys = $timesheetApprovals
            ->filter(static fn (TimesheetApproval $a): bool => $a->status === TimesheetApproval::STATUS_APPROVED
                && (int) ($a->getAttributes()['clock_in_entry_id'] ?? $a->clock_in_entry_id ?? 0) > 0)
            ->keyBy(static fn (TimesheetApproval $a) => AdminTimesheetApproval::approvalSessionLookupKeyFor($a));

        $results = [];

        foreach ($employees as $employee) {
            if ($employee->employment_status !== 'active') {
                continue;
            }

            $scheduleShifts = $employee->scheduleShifts ?? collect();
            $wageSkip = self::missingWageSkipReason($employee, $scheduleShifts);
            if ($wageSkip !== null) {
                $results[] = [
                    'employee' => $employee,
                    'total_hours' => 0,
                    'total_amount' => 0,
                    'lines' => [],
                    'sick_leave_accrued' => 0,
                    'annual_leave_accrued' => 0,
                    'skipped_reason' => $wageSkip,
                ];

                continue;
            }

            $rates = PayrollEmployeeRates::forEmployee($connection, $employee);
            $roster = PayrollRosterSummary::forEmployee($employee, $fortnightStart, $fortnightEnd);
            $leaveRecords = $employee->leaveRecords ?? collect();

            // Keep the loader’s ±1 day buffer so a shift that crosses midnight or the
            // fortnight boundary can still be paired, then clipped to this pay period.
            $loadedEntries = ($employee->timeClockEntries ?? collect())
                ->filter(static fn (TimeClockEntry $entry): bool => $entry->clocked_at !== null)
                ->values();

            $allSessions = PayrollCalculator::extractSessions(
                $loadedEntries,
                $rangeStart,
                $rangeEnd,
                $tz,
            );
            $entries = self::clockEntriesForApprovedSessions(
                $loadedEntries,
                $allSessions,
                $approvalKeys,
                (int) $employee->id,
                $requireApproved,
            );

            $leaveLines = PayrollLeaveProcessor::buildPayLines(
                $employee,
                $leaveRecords,
                $rates,
                $fortnightStart,
                $fortnightEnd,
            );

            $overlappingSessions = PayrollCalculator::extractSessions(
                $entries,
                $rangeStart,
                $rangeEnd,
                $tz,
            );

            if ($overlappingSessions === [] && $leaveLines === []) {
                $skippedReason = self::fortnightSkipReason(
                    $loadedEntries,
                    $allSessions,
                    $requireApproved,
                    $approvalKeys,
                    (int) $employee->id,
                    $fortnightStart,
                    $fortnightEnd,
                );

                $results[] = self::previewRow($employee, $roster, 0, 0, [], 0, 0, 0, 0, $skippedReason);

                continue;
            }

            $calc = PayrollCalculator::calculateForEmployee(
                $employee,
                $entries,
                $publicHolidays,
                $rates,
                $rangeStart,
                $rangeEnd,
                $scheduleShifts,
            );

            if ($calc['total_hours'] <= 0 && $leaveLines === []) {
                $results[] = self::previewRow(
                    $employee,
                    $roster,
                    0,
                    0,
                    [],
                    0,
                    0,
                    0,
                    0,
                    'Clock punches could not be paired into completed sessions',
                );

                continue;
            }

            $lines = $calc['lines'];
            foreach ($leaveLines as $leaveLine) {
                $lines[] = $leaveLine;
            }

            usort($lines, static fn (array $a, array $b): int => ($a['sort_order'] ?? 0) <=> ($b['sort_order'] ?? 0));

            $totalAmount = PayrollLineTotals::summarize($lines)['gross_pay'];

            $results[] = self::previewRow(
                $employee,
                $roster,
                (float) $calc['total_hours'],
                $totalAmount,
                $lines,
                (float) $calc['sick_leave_accrued'],
                (float) $calc['annual_leave_accrued'],
                (float) ($calc['sick_leave_accrued_amount'] ?? 0),
                (float) ($calc['annual_leave_accrued_amount'] ?? 0),
                null,
                $leaveLines,
            );
        }

        usort($results, static fn (array $a, array $b): int => strcasecmp(
            (string) ($a['employee']->full_legal_name ?? $a['employee']->email),
            (string) ($b['employee']->full_legal_name ?? $b['employee']->email)
        ));

        return $results;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  list<array<string, mixed>>  $leaveLines
     * @return array<string, mixed>
     */
    private static function previewRow(
        Employee $employee,
        array $roster,
        float $totalHours,
        float $totalAmount,
        array $lines,
        float $sickAccrued,
        float $annualAccrued,
        float $sickAccruedAmount,
        float $annualAccruedAmount,
        ?string $skippedReason,
        array $leaveLines = [],
    ): array {
        return [
            'employee' => $employee,
            'total_hours' => $totalHours,
            'total_amount' => $totalAmount,
            'lines' => $lines,
            'scheduled_hours' => (float) ($roster['scheduled_hours'] ?? 0),
            'roster_variance' => PayrollRosterSummary::varianceLabel($totalHours, (float) ($roster['scheduled_hours'] ?? 0)),
            'roster_label' => (string) ($roster['roster_label'] ?? ''),
            'sick_leave_accrued' => $sickAccrued,
            'annual_leave_accrued' => $annualAccrued,
            'sick_leave_accrued_amount' => $sickAccruedAmount,
            'annual_leave_accrued_amount' => $annualAccruedAmount,
            'leave_lines' => $leaveLines,
            'skipped_reason' => $skippedReason,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     * @return list<array<string, mixed>>
     */
    public static function payableRows(array $previewRows): array
    {
        return array_values(array_filter(
            $previewRows,
            static fn (array $r): bool => ($r['skipped_reason'] ?? null) === null
                && (($r['total_hours'] ?? 0) > 0 || ($r['total_amount'] ?? 0) > 0)
        ));
    }

    /**
     * Human-readable summary of why a pay run cannot be generated.
     *
     * @param  list<array<string, mixed>>  $previewRows
     */
    public static function summarizeBlockers(array $previewRows): string
    {
        if ($previewRows === []) {
            return 'No active employees found in this organization.';
        }

        $counts = [];
        foreach ($previewRows as $row) {
            $reason = $row['skipped_reason'] ?? null;
            if ($reason === null && (($row['total_hours'] ?? 0) > 0 || ($row['total_amount'] ?? 0) > 0)) {
                continue;
            }
            $label = $reason ?? 'No payable hours calculated';
            $counts[$label] = ($counts[$label] ?? 0) + 1;
        }

        if ($counts === []) {
            return 'No payable hours found for this fortnight.';
        }

        $parts = [];
        foreach ($counts as $label => $count) {
            $parts[] = "{$count} employee(s): {$label}";
        }

        $requireApproved = (bool) config('payroll.require_approved_timesheets', true);
        $hint = $requireApproved
            ? 'Approve each worked shift under Time clock records. Payroll only includes HR-approved clock time.'
            : 'Set PAYROLL_REQUIRE_APPROVED_TIMESHEETS=false in .env to include unapproved clock time (not recommended for production).';

        return implode('; ', $parts).'. '.$hint;
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     * @return array{payable: int, blocked: int, reasons: array<string, int>}
     */
    public static function blockerStats(array $previewRows): array
    {
        $stats = ['payable' => 0, 'blocked' => 0, 'reasons' => []];

        foreach ($previewRows as $row) {
            if (($row['skipped_reason'] ?? null) === null
                && (($row['total_hours'] ?? 0) > 0 || ($row['total_amount'] ?? 0) > 0)) {
                $stats['payable']++;
            } else {
                $stats['blocked']++;
                $label = $row['skipped_reason'] ?? 'No payable hours';
                $stats['reasons'][$label] = ($stats['reasons'][$label] ?? 0) + 1;
            }
        }

        return $stats;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\EmployeeScheduleShift>  $scheduleShifts
     */
    public static function missingWageSkipReason(Employee $employee, Collection $scheduleShifts): ?string
    {
        if (PayrollEmployeeRates::employeeUsesTitleWages($employee, $scheduleShifts)) {
            return null;
        }

        if (self::employeeHasAwardBand($employee)) {
            return null;
        }

        return 'No job title wage — set an hourly wage on the job title for this shift';
    }

    /**
     * Pair complete clock sessions first, then keep punches that belong to HR-approved
     * sessions. Filtering punches before pairing drops clock-outs and blocks payrun.
     *
     * @param  Collection<int, TimeClockEntry>  $loadedEntries
     * @param  list<array<string, mixed>>  $sessions
     * @param  Collection<string, TimesheetApproval>  $approvalKeys
     * @return Collection<int, TimeClockEntry>
     */
    public static function clockEntriesForApprovedSessions(
        Collection $loadedEntries,
        array $sessions,
        Collection $approvalKeys,
        int $employeeId,
        bool $requireApproved,
    ): Collection {
        if ($loadedEntries->isEmpty()) {
            return $loadedEntries;
        }

        $approvedSessions = [];
        foreach ($sessions as $session) {
            if ($requireApproved && ! self::sessionIsApproved($session, $approvalKeys, $employeeId)) {
                continue;
            }
            $approvedSessions[] = $session;
        }

        if ($approvedSessions === []) {
            return collect();
        }

        return $loadedEntries
            ->filter(static function (TimeClockEntry $entry) use ($approvedSessions): bool {
                if ($entry->clocked_at === null) {
                    return false;
                }

                $ts = $entry->clocked_at->getTimestamp();
                foreach ($approvedSessions as $session) {
                    $start = $session['original_in'] ?? $session['clock_in'] ?? null;
                    $end = $session['original_out'] ?? $session['clock_out'] ?? null;
                    if (! $start instanceof \Carbon\CarbonInterface || ! $end instanceof \Carbon\CarbonInterface) {
                        continue;
                    }
                    if ($ts >= $start->getTimestamp() && $ts <= $end->getTimestamp()) {
                        return true;
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  array<string, mixed>  $session
     * @param  Collection<string, TimesheetApproval>  $approvalKeys
     */
    public static function sessionIsApproved(array $session, Collection $approvalKeys, int $employeeId): bool
    {
        $clockInId = (int) ($session['clock_in_entry_id'] ?? 0);
        if ($clockInId <= 0) {
            return false;
        }

        return $approvalKeys->has(AdminTimesheetApproval::approvalSessionLookupKey($employeeId, $clockInId));
    }

    private static function employeeHasAwardBand(Employee $employee): bool
    {
        if (in_array($employee->employment_type, PayrollRateTypes::employmentTypes(), true)
            && in_array($employee->award_level, PayrollRateTypes::awardLevels(), true)) {
            return true;
        }

        $titles = collect();
        if ($employee->relationLoaded('assignedJobTitle') && $employee->assignedJobTitle !== null) {
            $titles->push($employee->assignedJobTitle);
        } elseif ($employee->exists) {
            $employee->loadMissing('assignedJobTitle');
            if ($employee->assignedJobTitle !== null) {
                $titles->push($employee->assignedJobTitle);
            }
        }

        if ($employee->relationLoaded('jobTitles')) {
            $titles = $titles->concat($employee->jobTitles);
        }

        foreach ($titles as $title) {
            if (! $title instanceof \App\Models\JobTitle) {
                continue;
            }
            if (in_array($title->employment_type, PayrollRateTypes::employmentTypes(), true)
                && in_array($title->award_level, PayrollRateTypes::awardLevels(), true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entriesInFortnight
     * @param  list<array<string, mixed>>  $sessions
     * @param  Collection<string, TimesheetApproval>  $approvalKeys
     */
    private static function fortnightSkipReason(
        Collection $entriesInFortnight,
        array $sessions,
        bool $requireApproved,
        Collection $approvalKeys,
        int $employeeId,
        string $fortnightStart,
        string $fortnightEnd,
    ): string {
        if ($entriesInFortnight->isEmpty()) {
            return 'No clock punches in this fortnight — check the selected pay period matches when employees worked';
        }

        if ($sessions === []) {
            return 'Clock punches could not be paired into completed sessions';
        }

        if (! $requireApproved) {
            return 'No clock time in fortnight';
        }

        $unapprovedSessions = [];
        foreach ($sessions as $session) {
            if (self::sessionIsApproved($session, $approvalKeys, $employeeId)) {
                continue;
            }

            $at = $session['original_in'] ?? $session['clock_in'] ?? null;
            if (! $at instanceof \Carbon\CarbonInterface) {
                continue;
            }

            $workDate = $at->copy()->timezone(DisplayTimezone::name())->toDateString();
            if ($workDate < $fortnightStart || $workDate > $fortnightEnd) {
                continue;
            }

            $unapprovedSessions[] = AdminTimesheetApproval::formatDayLabel($at);
        }

        if ($unapprovedSessions !== []) {
            return 'Timesheet shift(s) not approved: '.implode('; ', array_values(array_unique($unapprovedSessions)));
        }

        return 'No approved clock time in fortnight — approve each shift under Time clock records';
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     */
    public static function persistRun(
        string $connection,
        string $fortnightStart,
        string $fortnightEnd,
        array $previewRows,
        string $generatedBy,
        bool $finalize = false,
    ): PayrollRun {
        PayrollAwardRateSeeder::ensureDefaults($connection);

        $existing = PayrollRun::on($connection)->where('fortnight_start', $fortnightStart)->first();
        if ($existing !== null && $existing->status === PayrollRun::STATUS_FINALIZED) {
            return $existing->fresh(['lines.employee']);
        }

        /** @var PayrollRun $run */
        $run = PayrollRun::on($connection)->updateOrCreate(
            ['fortnight_start' => $fortnightStart],
            [
                'fortnight_end' => $fortnightEnd,
                'status' => $finalize ? PayrollRun::STATUS_FINALIZED : PayrollRun::STATUS_DRAFT,
                'generated_at' => now('UTC'),
                'generated_by' => $generatedBy,
            ]
        );

        PayrollRunLine::on($connection)->where('payroll_run_id', $run->id)->delete();

        foreach ($previewRows as $row) {
            /** @var Employee $employee */
            $employee = $row['employee'];
            if (($row['skipped_reason'] ?? null) !== null) {
                continue;
            }
            if (($row['total_hours'] ?? 0) <= 0 && ($row['total_amount'] ?? 0) <= 0) {
                continue;
            }

            foreach ($row['lines'] as $line) {
                PayrollRunLine::on($connection)->create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'rate_type' => $line['rate_type'],
                    'description' => $line['label'],
                    'hours' => $line['hours'],
                    'rate' => $line['rate'],
                    'amount' => $line['amount'],
                    'sort_order' => $line['sort_order'] ?? 0,
                ]);
            }

            if ($finalize) {
                PayrollLeaveProcessor::applyFinalize(
                    $employee,
                    (array) ($row['leave_lines'] ?? []),
                    $connection
                );

                foreach ($row['leave_lines'] ?? [] as $leaveLine) {
                    $recordId = (int) ($leaveLine['leave_record_id'] ?? 0);
                    if ($recordId > 0) {
                        EmployeeLeaveRecord::on($connection)->whereKey($recordId)->update([
                            'payroll_run_id' => $run->id,
                        ]);
                    }
                }

                $employee->forceFill([
                    'sick_leave_balance_hours' => round((float) $employee->sick_leave_balance_hours + (float) $row['sick_leave_accrued'], 2),
                    'annual_leave_balance_hours' => round((float) $employee->annual_leave_balance_hours + (float) $row['annual_leave_accrued'], 2),
                    'sick_leave_balance_amount' => round((float) $employee->sick_leave_balance_amount + (float) ($row['sick_leave_accrued_amount'] ?? 0), 2),
                    'annual_leave_balance_amount' => round((float) $employee->annual_leave_balance_amount + (float) ($row['annual_leave_accrued_amount'] ?? 0), 2),
                ])->save();
            }
        }

        return $run->fresh(['lines.employee']);
    }

    /**
     * @return array<string, array<string, array<string, PayrollAwardRate|null>>>
     */
    public static function groupRatesForDisplay(Collection $rates): array
    {
        $grouped = [];
        foreach (PayrollRateTypes::employmentTypes() as $employmentType) {
            foreach (PayrollRateTypes::awardLevels() as $awardLevel) {
                foreach (PayrollRateTypes::awardRateKeys() as $rateType) {
                    $grouped[$employmentType][$awardLevel][$rateType] = null;
                }
            }
        }

        foreach ($rates as $rate) {
            $grouped[$rate->employment_type][$rate->award_level][$rate->rate_type] = $rate;
        }

        return $grouped;
    }

    public static function formatMoney(float $amount): string
    {
        return '$'.number_format($amount, 2);
    }

    /**
     * Payable lines only. Accrual valuation is not pay and must not appear on the printed amount.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<string, mixed>>
     */
    public static function payableLines(array $lines): array
    {
        return array_values(array_filter($lines, static function (array $line): bool {
            $isEarning = PayrollLineTotals::categoryFor((string) ($line['rate_type'] ?? '')) === 'earning';

            return $isEarning && ((float) ($line['amount'] ?? 0) > 0 || (float) ($line['hours'] ?? 0) > 0);
        }));
    }

    /**
     * Printed hours × job-title wage = amount.
     *
     * @param  array<string, mixed>  $line
     */
    public static function formatPayLine(array $line): string
    {
        $hours = round((float) ($line['hours'] ?? 0), 2);
        $rate = round((float) ($line['rate'] ?? 0), 2);
        $amount = round((float) ($line['amount'] ?? 0), 2);

        if ($hours > 0 && $rate > 0) {
            return number_format($hours, 2).'h × '.self::formatMoney($rate).' = '.self::formatMoney($amount);
        }

        if ($amount > 0) {
            return self::formatMoney($amount);
        }

        return number_format($hours, 2).'h';
    }
}
