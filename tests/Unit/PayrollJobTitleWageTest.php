<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\JobTitle;
use App\Models\PublicHoliday;
use App\Models\TimeClockEntry;
use App\Support\AdminPayroll;
use App\Support\PayrollCalculator;
use App\Support\PayrollRateTypes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PayrollJobTitleWageTest extends TestCase
{
    private const TZ = 'Australia/Sydney';

    /**
     * Award rates are deliberately different from job-title wages.
     * Title-wage payroll must ignore them.
     *
     * @return array<string, float>
     */
    private function decoyAwardRates(): array
    {
        return [
            PayrollRateTypes::WEEKDAY_ORDINARY => 99.99,
            PayrollRateTypes::WEEKDAY_PENALTY => 111.11,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 120.00,
            PayrollRateTypes::SATURDAY => 150.00,
            PayrollRateTypes::SUNDAY => 180.00,
            PayrollRateTypes::PUBLIC_HOLIDAY => 200.00,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 130.00,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 160.00,
            PayrollRateTypes::OVERTIME_SUNDAY => 180.00,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 220.00,
        ];
    }

    private function title(int $id, string $name, float $wage): JobTitle
    {
        $title = new JobTitle([
            'name' => $name,
            'hourly_wage' => $wage,
        ]);
        $title->id = $id;

        return $title;
    }

    /**
     * @param  list<JobTitle>  $titles
     */
    private function employee(string $name, JobTitle $primary, array $titles = []): Employee
    {
        $employee = new Employee([
            'full_legal_name' => $name,
            'employment_type' => 'casual',
            'award_level' => 'level_1',
            'job_title_id' => $primary->id,
        ]);
        $employee->id = $primary->id;
        $employee->setRelation('assignedJobTitle', $primary);
        $employee->setRelation('jobTitles', new Collection($titles === [] ? [$primary] : $titles));

        return $employee;
    }

    private function punch(string $event, Carbon $at): TimeClockEntry
    {
        return new TimeClockEntry([
            'event_type' => $event,
            'clocked_at' => $at->copy()->timezone('UTC'),
        ]);
    }

    /**
     * @return list<TimeClockEntry>
     */
    private function clockSession(Carbon $start, Carbon $end): array
    {
        return [
            $this->punch(TimeClockEntry::EVENT_CLOCK_IN, $start),
            $this->punch(TimeClockEntry::EVENT_CLOCK_OUT, $end),
        ];
    }

    private function schedule(string $date, string $start, string $end, JobTitle $title): EmployeeScheduleShift
    {
        $shift = new EmployeeScheduleShift([
            'scheduled_date' => $date,
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => $start,
            'end_time' => $end,
            'job_title_id' => $title->id,
        ]);
        $shift->setRelation('jobTitle', $title);

        return $shift;
    }

    /**
     * @param  Collection<int, TimeClockEntry>  $entries
     * @param  Collection<int, EmployeeScheduleShift>|null  $scheduleShifts
     * @param  Collection<int, PublicHoliday>|null  $holidays
     * @return array<string, mixed>
     */
    private function pay(
        Employee $employee,
        Collection $entries,
        ?Collection $scheduleShifts = null,
        ?Collection $holidays = null,
    ): array {
        return PayrollCalculator::calculateForEmployee(
            $employee,
            $entries,
            $holidays ?? new Collection(),
            $this->decoyAwardRates(),
            Carbon::parse('2025-07-07', self::TZ),
            Carbon::parse('2025-07-20', self::TZ),
            $scheduleShifts,
        );
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<array<string, mixed>>
     */
    private function wageLines(array $result): array
    {
        return AdminPayroll::payableLines($result['lines']);
    }

    public function test_eight_clocked_hours_at_cook_wage_prints_260(): void
    {
        $cook = $this->title(1, 'Cook', 32.50);
        $employee = $this->employee('Alex Cook', $cook);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);

        $result = $this->pay($employee, new Collection($this->clockSession($start, $start->copy()->addHours(8))));

        $this->assertSame(8.0, $result['total_hours']);
        $this->assertSame(260.0, $result['total_amount']);
        $this->assertSame('$260.00', AdminPayroll::formatMoney($result['total_amount']));

        $lines = $this->wageLines($result);
        $this->assertCount(1, $lines);
        $this->assertSame('Cook', $lines[0]['label']);
        $this->assertSame(8.0, $lines[0]['hours']);
        $this->assertSame(32.5, $lines[0]['rate']);
        $this->assertSame(260.0, $lines[0]['amount']);
        $this->assertSame('8.00h × $32.50 = $260.00', AdminPayroll::formatPayLine($lines[0]));
        $this->assertSame([], $this->awardLines($result));
    }

    public function test_partial_clock_hours_use_job_title_wage_only(): void
    {
        $title = $this->title(2, 'Dishwasher', 24.80);
        $employee = $this->employee('Sam Lee', $title);
        $start = Carbon::parse('2025-07-08 10:15:00', self::TZ);

        $result = $this->pay($employee, new Collection($this->clockSession($start, $start->copy()->addMinutes(270))));

        // 10:15–14:45 = 4.50h × $24.80 = $111.60
        $this->assertSame(4.5, $result['total_hours']);
        $this->assertSame(111.6, $result['total_amount']);
        $this->assertSame('4.50h × $24.80 = $111.60', AdminPayroll::formatPayLine($this->wageLines($result)[0]));
        $this->assertSame('$111.60', AdminPayroll::formatMoney($result['total_amount']));
    }

    public function test_multiple_clock_sessions_are_summed_then_paid_at_title_wage(): void
    {
        $title = $this->title(3, 'Barista', 29.00);
        $employee = $this->employee('Jordan Pike', $title);
        $morning = Carbon::parse('2025-07-09 08:00:00', self::TZ);
        $afternoon = Carbon::parse('2025-07-09 13:00:00', self::TZ);

        $entries = new Collection([
            ...$this->clockSession($morning, $morning->copy()->addHours(4)),
            ...$this->clockSession($afternoon, $afternoon->copy()->addMinutes(270)),
        ]);

        $result = $this->pay($employee, $entries);

        // 4.00h + 4.50h = 8.50h × $29.00 = $246.50
        $this->assertSame(8.5, $result['total_hours']);
        $this->assertSame(246.5, $result['total_amount']);
        $this->assertSame('8.50h × $29.00 = $246.50', AdminPayroll::formatPayLine($this->wageLines($result)[0]));
    }

    public function test_unpaid_break_minutes_are_removed_before_wage_is_applied(): void
    {
        $title = $this->title(4, 'Waiter', 27.25);
        $employee = $this->employee('Casey Ng', $title);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);

        $entries = new Collection([
            $this->punch(TimeClockEntry::EVENT_CLOCK_IN, $start),
            $this->punch(TimeClockEntry::EVENT_BREAK_START, $start->copy()->addHours(4)),
            $this->punch(TimeClockEntry::EVENT_BREAK_END, $start->copy()->addHours(4)->addMinutes(30)),
            $this->punch(TimeClockEntry::EVENT_CLOCK_OUT, $start->copy()->addHours(8)),
        ]);

        $result = $this->pay($employee, $entries);

        // 8.00h on site − 0.50h unpaid break = 7.50h × $27.25 = $204.38
        $this->assertSame(7.5, $result['total_hours']);
        $this->assertSame(204.38, $result['total_amount']);
        $this->assertSame('7.50h × $27.25 = $204.38', AdminPayroll::formatPayLine($this->wageLines($result)[0]));
    }

    public function test_each_scheduled_job_title_is_paid_at_its_own_wage(): void
    {
        $cook = $this->title(5, 'Cook', 32.00);
        $barista = $this->title(6, 'Barista', 22.00);
        $employee = $this->employee('Riley Chen', $cook, [$cook, $barista]);

        $monday = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $tuesday = Carbon::parse('2025-07-08 14:00:00', self::TZ);

        $entries = new Collection([
            ...$this->clockSession($monday, $monday->copy()->addHours(4)),
            ...$this->clockSession($tuesday, $tuesday->copy()->addMinutes(270)),
        ]);
        $schedule = new Collection([
            $this->schedule('2025-07-07', '09:00', '13:00', $cook),
            $this->schedule('2025-07-08', '14:00', '18:30', $barista),
        ]);

        $result = $this->pay($employee, $entries, $schedule);

        // Cook 4.00h × $32.00 = $128.00
        // Barista 4.50h × $22.00 = $99.00
        // Total 8.50h = $227.00
        $this->assertSame(8.5, $result['total_hours']);
        $this->assertSame(227.0, $result['total_amount']);
        $this->assertSame('$227.00', AdminPayroll::formatMoney($result['total_amount']));

        $byTitle = collect($this->wageLines($result))->keyBy('label');
        $this->assertSame(4.0, $byTitle['Cook']['hours']);
        $this->assertSame(32.0, $byTitle['Cook']['rate']);
        $this->assertSame(128.0, $byTitle['Cook']['amount']);
        $this->assertSame(4.5, $byTitle['Barista']['hours']);
        $this->assertSame(22.0, $byTitle['Barista']['rate']);
        $this->assertSame(99.0, $byTitle['Barista']['amount']);
        $this->assertSame('4.00h × $32.00 = $128.00', AdminPayroll::formatPayLine($byTitle['Cook']));
        $this->assertSame('4.50h × $22.00 = $99.00', AdminPayroll::formatPayLine($byTitle['Barista']));
        $this->assertSame(227.0, round($byTitle['Cook']['amount'] + $byTitle['Barista']['amount'], 2));
    }

    public function test_weekend_and_public_holiday_hours_still_use_job_title_wage(): void
    {
        $title = $this->title(7, 'Chef', 31.10);
        $employee = $this->employee('Morgan Hale', $title);
        $saturday = Carbon::parse('2025-07-12 10:00:00', self::TZ);
        $holiday = Carbon::parse('2025-07-14 09:00:00', self::TZ);

        $entries = new Collection([
            ...$this->clockSession($saturday, $saturday->copy()->addHours(4)),
            ...$this->clockSession($holiday, $holiday->copy()->addHours(3)),
        ]);
        $holidays = new Collection([
            new PublicHoliday(['holiday_date' => '2025-07-14', 'name' => 'Test Holiday']),
        ]);

        $result = $this->pay($employee, $entries, null, $holidays);

        // Saturday 4.00h + holiday Monday 3.00h = 7.00h × $31.10 = $217.70
        // Not $150 Saturday or $200 public-holiday award rates.
        $this->assertSame(7.0, $result['total_hours']);
        $this->assertSame(217.7, $result['total_amount']);
        $this->assertSame('7.00h × $31.10 = $217.70', AdminPayroll::formatPayLine($this->wageLines($result)[0]));
        $this->assertSame([], $this->awardLines($result));
    }

    public function test_two_employees_are_paid_from_their_own_clock_hours_and_wages(): void
    {
        $chef = $this->title(8, 'Chef', 40.00);
        $hand = $this->title(9, 'Kitchen hand', 21.50);
        $alex = $this->employee('Alex Rivera', $chef);
        $sam = $this->employee('Sam Ortiz', $hand);

        $alexStart = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $samStart = Carbon::parse('2025-07-07 08:00:00', self::TZ);

        $alexPay = $this->pay($alex, new Collection($this->clockSession($alexStart, $alexStart->copy()->addMinutes(375))));
        $samPay = $this->pay($sam, new Collection($this->clockSession($samStart, $samStart->copy()->addHours(3))));

        // Alex 6.25h × $40.00 = $250.00
        $this->assertSame(6.25, $alexPay['total_hours']);
        $this->assertSame(250.0, $alexPay['total_amount']);
        $this->assertSame('$250.00', AdminPayroll::formatMoney($alexPay['total_amount']));
        $this->assertSame('6.25h × $40.00 = $250.00', AdminPayroll::formatPayLine($this->wageLines($alexPay)[0]));

        // Sam 3.00h × $21.50 = $64.50
        $this->assertSame(3.0, $samPay['total_hours']);
        $this->assertSame(64.5, $samPay['total_amount']);
        $this->assertSame('$64.50', AdminPayroll::formatMoney($samPay['total_amount']));
        $this->assertSame('3.00h × $21.50 = $64.50', AdminPayroll::formatPayLine($this->wageLines($samPay)[0]));

        $this->assertSame('$314.50', AdminPayroll::formatMoney($alexPay['total_amount'] + $samPay['total_amount']));
    }

    public function test_open_clock_in_and_punches_outside_the_fortnight_pay_nothing(): void
    {
        $title = $this->title(10, 'Cook', 30.00);
        $employee = $this->employee('Open Punch', $title);
        $inside = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $before = Carbon::parse('2025-07-06 09:00:00', self::TZ);

        $openOnly = $this->pay($employee, new Collection([
            $this->punch(TimeClockEntry::EVENT_CLOCK_IN, $inside),
        ]));
        $this->assertSame(0.0, $openOnly['total_hours']);
        $this->assertSame(0.0, $openOnly['total_amount']);
        $this->assertSame('$0.00', AdminPayroll::formatMoney($openOnly['total_amount']));
        $this->assertSame([], $this->wageLines($openOnly));

        $outside = $this->pay($employee, new Collection($this->clockSession($before, $before->copy()->addHours(8))));
        $this->assertSame(0.0, $outside['total_hours']);
        $this->assertSame(0.0, $outside['total_amount']);
        $this->assertSame([], $this->wageLines($outside));
    }

    public function test_short_clock_sessions_round_hours_once_then_multiply_wage(): void
    {
        $title = $this->title(11, 'Runner', 18.75);
        $employee = $this->employee('Pat Short', $title);
        $day = Carbon::parse('2025-07-07 09:00:00', self::TZ);

        $one = $this->pay($employee, new Collection($this->clockSession($day, $day->copy()->addMinutes(20))));
        // 20 minutes = 0.33h × $30 would be wrong; wage is $18.75 → 0.33 × 18.75 = $6.19
        $this->assertSame(0.33, $one['total_hours']);
        $this->assertSame(6.19, $one['total_amount']);
        $this->assertSame('0.33h × $18.75 = $6.19', AdminPayroll::formatPayLine($this->wageLines($one)[0]));

        $entries = new Collection([
            ...$this->clockSession($day, $day->copy()->addMinutes(20)),
            ...$this->clockSession($day->copy()->addHour(), $day->copy()->addHour()->addMinutes(20)),
            ...$this->clockSession($day->copy()->addHours(2), $day->copy()->addHours(2)->addMinutes(20)),
        ]);
        $three = $this->pay($employee, $entries);

        // 3 × 20 minutes = 1.00h, not 0.33 + 0.33 + 0.33 = 0.99
        // 1.00h × $18.75 = $18.75
        $this->assertSame(1.0, $three['total_hours']);
        $this->assertSame(18.75, $three['total_amount']);
        $this->assertSame('1.00h × $18.75 = $18.75', AdminPayroll::formatPayLine($this->wageLines($three)[0]));
    }

    public function test_one_clock_session_is_split_across_scheduled_title_wages(): void
    {
        $cook = $this->title(13, 'Cook', 32.00);
        $barista = $this->title(14, 'Barista', 22.00);
        $employee = $this->employee('Split Shift', $cook, [$cook, $barista]);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);

        $result = $this->pay(
            $employee,
            new Collection($this->clockSession($start, $start->copy()->addHours(8))),
            new Collection([
                $this->schedule('2025-07-07', '09:00', '13:00', $cook),
                $this->schedule('2025-07-07', '13:00', '17:00', $barista),
            ]),
        );

        // One 09:00–17:00 punch, two rostered titles.
        // Cook 4.00h × $32.00 = $128.00
        // Barista 4.00h × $22.00 = $88.00
        $this->assertSame(8.0, $result['total_hours']);
        $this->assertSame(216.0, $result['total_amount']);

        $byTitle = collect($this->wageLines($result))->keyBy('label');
        $this->assertSame(4.0, $byTitle['Cook']['hours']);
        $this->assertSame(128.0, $byTitle['Cook']['amount']);
        $this->assertSame(4.0, $byTitle['Barista']['hours']);
        $this->assertSame(88.0, $byTitle['Barista']['amount']);
        $this->assertSame(216.0, round($byTitle['Cook']['amount'] + $byTitle['Barista']['amount'], 2));
    }

    public function test_overnight_clock_in_uses_previous_days_scheduled_title(): void
    {
        $night = $this->title(15, 'CAS1 (Penalty Rates)', 40.00);
        $day = $this->title(16, 'CAS1', 28.00);
        $employee = $this->employee('Night Shift', $day, [$day, $night]);
        $start = Carbon::parse('2025-07-08 00:30:00', self::TZ);

        $result = $this->pay(
            $employee,
            new Collection($this->clockSession($start, $start->copy()->addHours(5))),
            new Collection([
                $this->schedule('2025-07-07', '22:00', '06:00', $night),
            ]),
        );

        // Tuesday 00:30–05:30 still belongs to Monday 22:00–06:00 penalty title.
        // 5.00h × $40.00 = $200.00, not the primary $28.00 wage.
        $this->assertSame(5.0, $result['total_hours']);
        $this->assertSame(200.0, $result['total_amount']);
        $this->assertSame('CAS1 (Penalty Rates)', $this->wageLines($result)[0]['label']);
        $this->assertSame('5.00h × $40.00 = $200.00', AdminPayroll::formatPayLine($this->wageLines($result)[0]));
    }

    public function test_wage_before_effective_date_is_not_used(): void
    {
        $title = $this->title(17, 'Cook', 50.00);
        $title->wage_effective_from = '2025-07-14';
        $employee = $this->employee('Future Wage', $title);
        $before = Carbon::parse('2025-07-07 09:00:00', self::TZ);
        $after = Carbon::parse('2025-07-14 09:00:00', self::TZ);

        $early = $this->pay($employee, new Collection($this->clockSession($before, $before->copy()->addHours(8))));
        $this->assertSame(8.0, $early['total_hours']);
        $this->assertSame(0.0, $early['total_amount']);
        $this->assertSame('Wage not yet effective', $this->wageLines($early)[0]['label']);
        $this->assertSame(0.0, $this->wageLines($early)[0]['rate']);

        $later = $this->pay($employee, new Collection($this->clockSession($after, $after->copy()->addHours(2))));
        $this->assertSame(2.0, $later['total_hours']);
        $this->assertSame(100.0, $later['total_amount']);
        $this->assertSame('Cook', $this->wageLines($later)[0]['label']);
        $this->assertSame('2.00h × $50.00 = $100.00', AdminPayroll::formatPayLine($this->wageLines($later)[0]));
    }

    public function test_printed_pay_lines_exclude_leave_accrual_from_the_amount(): void
    {
        $title = $this->title(12, 'Cook', 32.50);
        $employee = $this->employee('Accrual Check', $title);
        $start = Carbon::parse('2025-07-07 09:00:00', self::TZ);

        $result = $this->pay($employee, new Collection($this->clockSession($start, $start->copy()->addHours(8))));

        $this->assertGreaterThan(0, $result['sick_leave_accrued']);
        $this->assertTrue(collect($result['lines'])->contains(
            fn (array $line): bool => $line['rate_type'] === PayrollRateTypes::SICK_LEAVE_ACCRUAL
        ));

        $printed = $this->wageLines($result);
        $this->assertCount(1, $printed);
        $this->assertSame(260.0, round(array_sum(array_column($printed, 'amount')), 2));
        $this->assertSame('$260.00', AdminPayroll::formatMoney($result['total_amount']));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return list<string>
     */
    private function awardLines(array $result): array
    {
        return collect($result['lines'])
            ->pluck('rate_type')
            ->intersect(PayrollRateTypes::awardRateKeys())
            ->values()
            ->all();
    }
}
