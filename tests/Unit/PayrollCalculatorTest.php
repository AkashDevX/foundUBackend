<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PublicHoliday;
use App\Models\TimeClockEntry;
use App\Support\PayrollCalculator;
use App\Support\PayrollRateTypes;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class PayrollCalculatorTest extends TestCase
{
    public function test_classifies_weekday_ordinary_hours(): void
    {
        $employee = new Employee([
            'employment_type' => 'full_time',
            'award_level' => 'level_2',
        ]);

        $tz = 'Australia/Sydney';
        $monday = Carbon::parse('2025-07-07 09:00:00', $tz);
        $entries = new Collection([
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
                'clocked_at' => $monday->copy()->timezone('UTC'),
            ]),
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
                'clocked_at' => $monday->copy()->addHours(4)->timezone('UTC'),
            ]),
        ]);

        $rates = [
            PayrollRateTypes::WEEKDAY_ORDINARY => 26.70,
            PayrollRateTypes::WEEKDAY_PENALTY => 30.71,
            PayrollRateTypes::SATURDAY => 40.05,
            PayrollRateTypes::SUNDAY => 53.40,
            PayrollRateTypes::PUBLIC_HOLIDAY => 66.75,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 40.05,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 53.40,
            PayrollRateTypes::OVERTIME_SUNDAY => 53.40,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 66.75,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 34.71,
        ];

        $result = PayrollCalculator::calculateForEmployee(
            $employee,
            $entries,
            new Collection(),
            $rates,
            Carbon::parse('2025-07-07', $tz),
            Carbon::parse('2025-07-20', $tz),
        );

        $this->assertSame(4.0, $result['total_hours']);
        $ordinary = collect($result['lines'])->firstWhere('rate_type', PayrollRateTypes::WEEKDAY_ORDINARY);
        $this->assertNotNull($ordinary);
        $this->assertSame(4.0, $ordinary['hours']);
        $this->assertSame(106.8, $ordinary['amount']);
    }

    public function test_applies_overtime_above_full_time_threshold(): void
    {
        $employee = new Employee([
            'employment_type' => 'full_time',
            'award_level' => 'level_1',
        ]);

        $tz = 'Australia/Sydney';
        $weekStart = Carbon::parse('2025-07-07 08:00:00', $tz);
        $entries = new Collection();

        for ($day = 0; $day < 5; $day++) {
            $start = $weekStart->copy()->addDays($day);
            $entries->push(new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
                'clocked_at' => $start->copy()->timezone('UTC'),
            ]));
            $entries->push(new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
                'clocked_at' => $start->copy()->addHours(8)->timezone('UTC'),
            ]));
        }

        $rates = [
            PayrollRateTypes::WEEKDAY_ORDINARY => 25.85,
            PayrollRateTypes::WEEKDAY_PENALTY => 29.73,
            PayrollRateTypes::WEEKDAY_MIDNIGHT_SHIFT => 33.61,
            PayrollRateTypes::SATURDAY => 38.78,
            PayrollRateTypes::SUNDAY => 51.70,
            PayrollRateTypes::PUBLIC_HOLIDAY => 64.63,
            PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H => 38.78,
            PayrollRateTypes::OVERTIME_MON_SAT_AFTER_2H => 51.70,
            PayrollRateTypes::OVERTIME_SUNDAY => 51.70,
            PayrollRateTypes::OVERTIME_PUBLIC_HOLIDAY => 64.63,
        ];

        $result = PayrollCalculator::calculateForEmployee(
            $employee,
            $entries,
            new Collection(),
            $rates,
            Carbon::parse('2025-07-07', $tz),
            Carbon::parse('2025-07-20', $tz),
        );

        $this->assertSame(40.0, $result['total_hours']);
        $otFirst = collect($result['lines'])->firstWhere('rate_type', PayrollRateTypes::OVERTIME_MON_SAT_FIRST_2H);
        $this->assertNotNull($otFirst);
        $this->assertSame(2.0, $otFirst['hours']);
    }

    public function test_public_holiday_rate_applies(): void
    {
        $employee = new Employee([
            'employment_type' => 'casual',
            'award_level' => 'level_1',
        ]);

        $tz = 'Australia/Sydney';
        $ph = Carbon::parse('2025-07-07 10:00:00', $tz);
        $entries = new Collection([
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_IN,
                'clocked_at' => $ph->copy()->timezone('UTC'),
            ]),
            new TimeClockEntry([
                'event_type' => TimeClockEntry::EVENT_CLOCK_OUT,
                'clocked_at' => $ph->copy()->addHours(2)->timezone('UTC'),
            ]),
        ]);

        $holiday = new PublicHoliday(['holiday_date' => '2025-07-07', 'name' => 'Test Day']);

        $rates = [
            PayrollRateTypes::PUBLIC_HOLIDAY => 71.09,
            PayrollRateTypes::WEEKDAY_ORDINARY => 32.31,
        ];

        $result = PayrollCalculator::calculateForEmployee(
            $employee,
            $entries,
            new Collection([$holiday]),
            $rates,
            Carbon::parse('2025-07-07', $tz),
            Carbon::parse('2025-07-20', $tz),
        );

        $phLine = collect($result['lines'])->firstWhere('rate_type', PayrollRateTypes::PUBLIC_HOLIDAY);
        $this->assertNotNull($phLine);
        $this->assertSame(2.0, $phLine['hours']);
    }
}
