<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Support\AdminWeeklySchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdminWeeklyScheduleTest extends TestCase
{
    public function test_build_schedule_renders_saved_shift_blocks(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $department = new Department(['name' => 'Retail']);
        $department->id = 1;

        $location = new WorkLocation(['name' => 'Rose City Shopping Centre']);
        $location->id = 2;

        $employee = new Employee([
            'public_id' => 'emp-1',
            'full_legal_name' => 'Aimee Fromm',
            'email' => 'aimee@example.com',
            'job_title' => 'PT Level 1',
        ]);
        $employee->id = 10;
        $employee->setRelation('assignedDepartment', $department);
        $employee->setRelation('workLocation', $location);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignedShift', null);
        $employee->setRelation('assignmentShifts', new Collection());

        $entry = new EmployeeScheduleShift([
            'employee_id' => 10,
            'scheduled_date' => '2026-06-16',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:30',
            'end_time' => '17:30',
            'department_id' => 1,
            'work_location_id' => 2,
        ]);
        $entry->id = 50;
        $entry->setRelation('shiftTemplate', new Shift([
            'name' => 'Morning retail',
            'breaks' => [
                ['label' => 'Lunch', 'minutes' => 30, 'paid' => false],
            ],
        ]));
        $entry->setRelation('jobTitle', null);
        $entry->setRelation('department', $department);
        $entry->setRelation('workLocation', $location);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection([$entry])
        );

        $this->assertSame(1, $schedule['stats']['shifts']);
        $this->assertSame('8h 00m', $schedule['stats']['scheduled_hours_label']);

        $row = $schedule['rows'][0];
        $tuesdayCell = $row['cells']['tue'];
        $tuesdayBlocks = $tuesdayCell['blocks'];
        $this->assertFalse($tuesdayCell['is_day_off']);
        $this->assertCount(1, $tuesdayBlocks);
        $this->assertSame(50, $tuesdayBlocks[0]['id']);
        $this->assertSame('shift', $tuesdayBlocks[0]['type']);
        $this->assertSame('30m unpaid Lunch', $tuesdayBlocks[0]['subtitle']);
        $this->assertSame('This date only', $tuesdayBlocks[0]['recurrence_label']);
        $this->assertSame(['is_day_off' => false, 'blocks' => []], $row['cells']['mon']);
    }

    public function test_build_schedule_shows_time_off_block(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $employee = new Employee([
            'public_id' => 'emp-2',
            'full_legal_name' => 'Sam Lee',
            'email' => 'sam@example.com',
        ]);
        $employee->id = 11;
        $employee->setRelation('assignedDepartment', null);
        $employee->setRelation('workLocation', null);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignedShift', null);
        $employee->setRelation('assignmentShifts', new Collection());

        $entry = new EmployeeScheduleShift([
            'employee_id' => 11,
            'scheduled_date' => '2026-06-17',
            'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF,
            'notes' => 'Annual leave',
        ]);
        $entry->id = 60;
        $entry->setRelation('shiftTemplate', null);
        $entry->setRelation('jobTitle', null);
        $entry->setRelation('department', null);
        $entry->setRelation('workLocation', null);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection([$entry])
        );

        $this->assertSame(1, $schedule['stats']['absences']);
        $wednesdayCell = $schedule['rows'][0]['cells']['wed'];
        $this->assertTrue($wednesdayCell['is_day_off']);
        $block = $wednesdayCell['blocks'][0];
        $this->assertSame('time_off', $block['type']);
        $this->assertSame('Day off', $block['title']);
        $this->assertSame('Annual leave', $block['subtitle']);
    }

    public function test_build_schedule_day_off_hides_shifts_and_suggestions_for_that_day(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $shift = new Shift([
            'name' => 'Morning',
            'start_time' => Carbon::parse('09:00'),
            'end_time' => Carbon::parse('17:00'),
            'shift_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        $employee = new Employee([
            'full_legal_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
        ]);
        $employee->id = 12;
        $employee->setRelation('assignedShift', $shift);
        $employee->setRelation('assignedDepartment', null);
        $employee->setRelation('workLocation', null);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignmentShifts', new Collection());

        $shiftEntry = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-06-16',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
        ]);
        $shiftEntry->id = 70;
        $shiftEntry->setRelation('shiftTemplate', $shift);
        $shiftEntry->setRelation('jobTitle', null);
        $shiftEntry->setRelation('department', null);
        $shiftEntry->setRelation('workLocation', null);

        $timeOffEntry = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-06-16',
            'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF,
            'notes' => 'Leave',
        ]);
        $timeOffEntry->id = 71;
        $timeOffEntry->setRelation('shiftTemplate', null);
        $timeOffEntry->setRelation('jobTitle', null);
        $timeOffEntry->setRelation('department', null);
        $timeOffEntry->setRelation('workLocation', null);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection([$shiftEntry, $timeOffEntry])
        );

        $tuesdayCell = $schedule['rows'][0]['cells']['tue'];
        $this->assertTrue($tuesdayCell['is_day_off']);
        $this->assertCount(1, $tuesdayCell['blocks']);
        $this->assertSame('time_off', $tuesdayCell['blocks'][0]['type']);
        $this->assertSame(0, $schedule['stats']['shifts']);
    }

    public function test_build_schedule_shows_assignment_suggestion_when_day_empty(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $shift = new Shift([
            'name' => 'Weekday only',
            'start_time' => Carbon::parse('08:00'),
            'end_time' => Carbon::parse('16:00'),
            'shift_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);

        $employee = new Employee([
            'full_legal_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
        ]);
        $employee->id = 12;
        $employee->setRelation('assignedShift', $shift);
        $employee->setRelation('assignedDepartment', null);
        $employee->setRelation('workLocation', null);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignmentShifts', new Collection());

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection()
        );

        $mondayCell = $schedule['rows'][0]['cells']['mon'];
        $mondayBlock = $mondayCell['blocks'][0];
        $this->assertFalse($mondayCell['is_day_off']);
        $this->assertTrue($mondayBlock['is_suggestion']);
        $this->assertSame('suggestion', $mondayBlock['type']);
        $this->assertSame(0, $schedule['stats']['shifts']);
    }

    public function test_build_schedule_shows_multiple_assignment_shift_suggestions_when_day_empty(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);

        $morning = new Shift([
            'name' => 'Morning',
            'start_time' => Carbon::parse('08:00'),
            'end_time' => Carbon::parse('12:00'),
            'shift_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);
        $morning->id = 1;

        $afternoon = new Shift([
            'name' => 'Afternoon',
            'start_time' => Carbon::parse('13:00'),
            'end_time' => Carbon::parse('17:00'),
            'shift_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        ]);
        $afternoon->id = 2;

        $employee = new Employee([
            'full_legal_name' => 'Alex Rivera',
            'email' => 'alex@example.com',
        ]);
        $employee->id = 12;
        $employee->setRelation('assignedShift', null);
        $employee->setRelation('assignedDepartment', null);
        $employee->setRelation('workLocation', null);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignmentShifts', new Collection([
            tap(new \App\Models\EmployeeAssignmentShift([
                'shift_id' => 1,
                'unpaid_break_minutes' => 30,
                'sort_order' => 0,
            ]), static function ($row) use ($morning): void {
                $row->setRelation('shiftTemplate', $morning);
            }),
            tap(new \App\Models\EmployeeAssignmentShift([
                'shift_id' => 2,
                'unpaid_break_minutes' => 0,
                'sort_order' => 1,
            ]), static function ($row) use ($afternoon): void {
                $row->setRelation('shiftTemplate', $afternoon);
            }),
        ]));

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection()
        );

        $mondayBlocks = $schedule['rows'][0]['cells']['mon']['blocks'];
        $this->assertCount(2, $mondayBlocks);
        $this->assertTrue($mondayBlocks[0]['is_suggestion']);
        $this->assertTrue($mondayBlocks[1]['is_suggestion']);
        $this->assertSame('', $mondayBlocks[0]['subtitle']);
        $this->assertSame('', $mondayBlocks[1]['subtitle']);
        $this->assertStringContainsString('30m unpaid break', $mondayBlocks[0]['meta']);
    }

    public function test_recurrence_dates_never_returns_the_start_date_only(): void
    {
        $this->assertSame(
            ['2026-06-16'],
            AdminWeeklySchedule::recurrenceDates('2026-06-16', 'never', ['mon', 'wed'], '2026-07-01')
        );
    }

    public function test_recurrence_dates_every_week_repeats_same_weekday(): void
    {
        $dates = AdminWeeklySchedule::recurrenceDates('2026-06-16', 'every_week', ['tue'], '2026-07-07');

        $this->assertSame(
            ['2026-06-16', '2026-06-23', '2026-06-30', '2026-07-07'],
            $dates
        );
    }

    public function test_recurrence_dates_every_2_weeks_uses_fortnightly_interval(): void
    {
        $this->assertSame(
            ['2026-06-16', '2026-06-30', '2026-07-14'],
            AdminWeeklySchedule::recurrenceDates('2026-06-16', 'every_2_weeks', ['tue'], '2026-07-14')
        );
    }

    public function test_recurrence_dates_every_week_uses_selected_days_and_ends(): void
    {
        $this->assertSame(
            ['2026-06-16', '2026-06-18', '2026-06-23', '2026-06-25'],
            AdminWeeklySchedule::recurrenceDates('2026-06-16', 'every_week', ['tue', 'thu'], '2026-06-25')
        );
    }

    public function test_recurrence_dates_weekly_books_selected_weekdays_through_until(): void
    {
        $this->assertSame(
            ['2026-06-16', '2026-06-18', '2026-06-19'],
            AdminWeeklySchedule::recurrenceDates('2026-06-16', 'weekly', ['tue', 'thu', 'fri'], '2026-06-19')
        );
    }

    public function test_recurrence_dates_weekly_defaults_to_end_of_week_and_caps_at_twelve_weeks(): void
    {
        $this->assertSame(
            ['2026-06-17', '2026-06-19', '2026-06-21'],
            AdminWeeklySchedule::recurrenceDates('2026-06-17', 'weekly', ['wed', 'fri', 'sun'])
        );

        $dates = AdminWeeklySchedule::recurrenceDates('2026-06-15', 'weekly', ['mon'], '2027-06-15');
        $this->assertSame('2026-06-15', $dates[0]);
        $this->assertSame('2026-09-07', $dates[array_key_last($dates)]);
        $this->assertCount(13, $dates);
    }

    public function test_recurrence_mode_options_exclude_no_end_date(): void
    {
        $options = AdminWeeklySchedule::recurrenceModeOptions();

        $this->assertSame([
            'never',
            'every_week',
            'every_2_weeks',
            'every_3_weeks',
            'every_4_weeks',
            'every_5_weeks',
            'every_6_weeks',
            'every_7_weeks',
            'every_8_weeks',
        ], array_keys($options));
        $this->assertArrayNotHasKey('no_end_date', $options);
        $this->assertArrayNotHasKey('this_week', $options);
        $this->assertArrayNotHasKey('ongoing', $options);
        $this->assertSame('Every week', AdminWeeklySchedule::recurrenceModeLabel('no_end_date'));
    }

    public function test_resolve_week_start_normalizes_to_monday(): void
    {
        $weekStart = AdminWeeklySchedule::resolveWeekStart('2026-06-18');

        $this->assertSame(Carbon::MONDAY, $weekStart->dayOfWeek);
        $this->assertSame('2026-06-15', $weekStart->toDateString());
    }
}
