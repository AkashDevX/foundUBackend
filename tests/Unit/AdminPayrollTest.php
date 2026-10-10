<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\TimeClockEntry;
use App\Models\TimesheetApproval;
use App\Support\AdminPayroll;
use App\Support\AdminTimesheetApproval;
use App\Support\PayrollAwardRateDefaults;
use App\Support\PayrollCalculator;
use App\Support\PayrollEmployeeRates;
use App\Support\PayrollRateTypes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminPayrollTest extends TestCase
{
    private const TZ = 'Australia/Brisbane';

    public function test_fortnight_end_is_thirteen_days_after_start(): void
    {
        $start = '2025-07-07';
        $this->assertSame('2025-07-20', AdminPayroll::fortnightEndForStart($start));
    }

    public function test_default_rates_include_all_employment_types(): void
    {
        $all = PayrollAwardRateDefaults::all();
        foreach (PayrollRateTypes::employmentTypes() as $type) {
            $this->assertArrayHasKey($type, $all);
            foreach (PayrollRateTypes::awardLevels() as $level) {
                $this->assertArrayHasKey($level, $all[$type]);
                $this->assertCount(count(PayrollRateTypes::awardRateKeys()), $all[$type][$level]);
            }
        }
    }

    public function test_normalize_fortnight_start_snaps_to_monday_pair(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-07-10', 'Australia/Brisbane'));
        $normalized = AdminPayroll::normalizeFortnightStart('2025-07-10');
        $this->assertSame(Carbon::MONDAY, (int) Carbon::parse($normalized)->dayOfWeek);
        Carbon::setTestNow();
    }

    public function test_fortnight_list_pages_older_periods_newest_first(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-08 12:00:00', self::TZ));
        config(['app.display_timezone' => self::TZ]);

        $current = AdminPayroll::currentFortnightStart();
        $this->assertSame(8, AdminPayroll::fortnightWindowCount($current, 1));
        $this->assertSame(16, AdminPayroll::fortnightWindowCount($current, 9));

        $older = Carbon::parse($current, self::TZ)->subWeeks(20)->toDateString();
        $this->assertSame(16, AdminPayroll::fortnightWindowCount($older, 8));
        $this->assertSame(208, AdminPayroll::fortnightWindowCount($current, 500));

        $rows = AdminPayroll::recentFortnights(16);
        $this->assertCount(16, $rows);
        $this->assertSame($current, $rows[0]['start']);
        $this->assertTrue($rows[0]['start'] > $rows[15]['start']);

        Carbon::setTestNow();
    }

    public function test_pay_line_labels_hide_internal_rate_keys_and_stay_sorted(): void
    {
        $this->assertSame('Hourly wage', AdminPayroll::payLineLabel([
            'rate_type' => 'job_title_12',
            'label' => 'job_title_12',
        ]));
        $this->assertSame('Cook', AdminPayroll::payLineLabel([
            'rate_type' => 'job_title_9',
            'label' => 'Cook',
        ]));
        $this->assertSame('Saturday', AdminPayroll::payLineLabel([
            'rate_type' => 'saturday',
            'label' => 'saturday',
        ]));

        $lines = AdminPayroll::payableLines([
            ['rate_type' => 'allowance', 'label' => 'Travel', 'hours' => 0, 'rate' => 10, 'amount' => 10],
            ['rate_type' => 'weekday_ordinary', 'label' => 'weekday_ordinary', 'hours' => 8, 'rate' => 25, 'amount' => 200],
            ['rate_type' => 'sunday', 'label' => 'Sunday', 'hours' => 4, 'rate' => 40, 'amount' => 160],
        ]);

        $this->assertSame(['weekday_ordinary', 'sunday', 'allowance'], array_column($lines, 'rate_type'));
        $this->assertSame('Mon–Fri ordinary (6am–6pm)', AdminPayroll::payLineLabel($lines[0]));
    }

    public function test_job_title_wage_is_enough_without_employment_type(): void
    {
        $title = new JobTitle(['name' => 'Cook', 'hourly_wage' => 32.50]);
        $title->id = 9;
        $employee = new Employee([
            'full_legal_name' => 'Alex Cook',
            'employment_status' => 'active',
            'job_title_id' => 9,
        ]);
        $employee->id = 9;
        $employee->setRelation('assignedJobTitle', $title);
        $employee->setRelation('jobTitles', new Collection([$title]));

        $this->assertTrue(PayrollEmployeeRates::employeeUsesTitleWages($employee, collect()));
        $this->assertNull(AdminPayroll::missingWageSkipReason($employee, collect()));
    }

    public function test_missing_wage_and_award_band_blocks_payrun(): void
    {
        $employee = new Employee([
            'full_legal_name' => 'No Rates',
            'employment_status' => 'active',
        ]);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('jobTitles', new Collection());

        $this->assertSame(
            'No hourly wage on this job title. Set the wage, then assign that title on the weekly schedule.',
            AdminPayroll::missingWageSkipReason($employee, collect())
        );
    }

    public function test_approved_session_keeps_clock_out_and_breaks(): void
    {
        config(['app.display_timezone' => self::TZ]);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $entries = $this->sessionEntries([
            [11, TimeClockEntry::EVENT_CLOCK_IN, $start],
            [12, TimeClockEntry::EVENT_BREAK_START, $start->copy()->addHours(4)],
            [13, TimeClockEntry::EVENT_BREAK_END, $start->copy()->addHours(4)->addMinutes(30)],
            [14, TimeClockEntry::EVENT_CLOCK_OUT, $start->copy()->addHours(8)],
        ]);

        $sessions = PayrollCalculator::extractSessions(
            $entries,
            Carbon::parse('2025-07-07', self::TZ)->startOfDay(),
            Carbon::parse('2025-07-20', self::TZ)->endOfDay(),
            self::TZ,
        );

        $this->assertCount(1, $sessions);
        $this->assertSame(11, $sessions[0]['clock_in_entry_id']);

        $approval = new TimesheetApproval([
            'employee_id' => 4,
            'clock_in_entry_id' => 11,
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);
        $approvalKeys = collect([$approval])
            ->keyBy(static fn (TimesheetApproval $row) => AdminTimesheetApproval::approvalSessionLookupKeyFor($row));

        $kept = AdminPayroll::clockEntriesForApprovedSessions($entries, $sessions, $approvalKeys, 4, true);

        $this->assertCount(4, $kept);
        $this->assertSame([11, 12, 13, 14], $kept->pluck('id')->all());
    }

    public function test_unapproved_session_is_excluded_from_payrun(): void
    {
        config(['app.display_timezone' => self::TZ]);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $entries = $this->sessionEntries([
            [21, TimeClockEntry::EVENT_CLOCK_IN, $start],
            [22, TimeClockEntry::EVENT_CLOCK_OUT, $start->copy()->addHours(8)],
        ]);

        $sessions = PayrollCalculator::extractSessions(
            $entries,
            Carbon::parse('2025-07-07', self::TZ)->startOfDay(),
            Carbon::parse('2025-07-20', self::TZ)->endOfDay(),
            self::TZ,
        );

        $kept = AdminPayroll::clockEntriesForApprovedSessions($entries, $sessions, collect(), 4, true);

        $this->assertCount(0, $kept);
        $this->assertFalse(AdminPayroll::sessionIsApproved($sessions[0], collect(), 4));
    }

    public function test_overnight_approved_clock_in_keeps_next_morning_clock_out(): void
    {
        config(['app.display_timezone' => self::TZ]);
        $clockInAt = Carbon::parse('2025-07-07 22:00:00', self::TZ);
        $clockOutAt = Carbon::parse('2025-07-08 06:00:00', self::TZ);
        $entries = $this->sessionEntries([
            [31, TimeClockEntry::EVENT_CLOCK_IN, $clockInAt],
            [32, TimeClockEntry::EVENT_CLOCK_OUT, $clockOutAt],
        ]);

        $sessions = PayrollCalculator::extractSessions(
            $entries,
            Carbon::parse('2025-07-07', self::TZ)->startOfDay(),
            Carbon::parse('2025-07-20', self::TZ)->endOfDay(),
            self::TZ,
        );

        $this->assertCount(1, $sessions);
        $this->assertSame(31, $sessions[0]['clock_in_entry_id']);

        $approval = new TimesheetApproval([
            'employee_id' => 8,
            'clock_in_entry_id' => 31,
            'status' => TimesheetApproval::STATUS_APPROVED,
        ]);
        $approvalKeys = collect([$approval])
            ->keyBy(static fn (TimesheetApproval $row) => AdminTimesheetApproval::approvalSessionLookupKeyFor($row));

        $kept = AdminPayroll::clockEntriesForApprovedSessions($entries, $sessions, $approvalKeys, 8, true);

        $this->assertCount(2, $kept);
        $this->assertSame([31, 32], $kept->pluck('id')->all());
    }

    /**
     * @param  list<array{0: int, 1: string, 2: Carbon}>  $rows
     * @return Collection<int, TimeClockEntry>
     */
    private function sessionEntries(array $rows): Collection
    {
        $entries = [];
        foreach ($rows as $row) {
            $entry = new TimeClockEntry([
                'event_type' => $row[1],
                'clocked_at' => $row[2]->copy()->timezone('UTC'),
            ]);
            $entry->id = $row[0];
            $entries[] = $entry;
        }

        return new Collection($entries);
    }
}
