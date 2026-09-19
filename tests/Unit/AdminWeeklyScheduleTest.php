<?php

namespace Tests\Unit;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentShift;
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
        $employee->setRelation('assignmentShifts', new Collection);

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
        $employee->setRelation('assignmentShifts', new Collection);

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

    public function test_build_schedule_day_off_hides_shifts_for_that_day(): void
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
        $employee->setRelation('assignmentShifts', new Collection);

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

    public function test_build_schedule_leaves_empty_days_blank_without_assignment_suggestions(): void
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
        $employee->setRelation('assignmentShifts', new Collection);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection
        );

        $mondayCell = $schedule['rows'][0]['cells']['mon'];
        $this->assertFalse($mondayCell['is_day_off']);
        $this->assertSame([], $mondayCell['blocks']);
        $this->assertSame(0, $schedule['stats']['shifts']);
        $this->assertSame([], $schedule['rows'][0]['cells']['sat']['blocks']);
    }

    public function test_build_schedule_does_not_show_multiple_assignment_shifts_on_empty_days(): void
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
            tap(new EmployeeAssignmentShift([
                'shift_id' => 1,
                'unpaid_break_minutes' => 30,
                'sort_order' => 0,
            ]), static function ($row) use ($morning): void {
                $row->setRelation('shiftTemplate', $morning);
            }),
            tap(new EmployeeAssignmentShift([
                'shift_id' => 2,
                'unpaid_break_minutes' => 0,
                'sort_order' => 1,
            ]), static function ($row) use ($afternoon): void {
                $row->setRelation('shiftTemplate', $afternoon);
            }),
        ]));

        $savedShift = new EmployeeScheduleShift([
            'employee_id' => 12,
            'scheduled_date' => '2026-06-16',
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '13:00',
            'job_title_id' => null,
        ]);
        $savedShift->id = 88;
        $savedShift->setRelation('shiftTemplate', $morning);
        $savedShift->setRelation('jobTitle', null);
        $savedShift->setRelation('department', null);
        $savedShift->setRelation('workLocation', null);
        $savedShift->setRelation('leaveType', null);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$employee]),
            $weekStart,
            new Collection([$savedShift])
        );

        $mondayBlocks = $schedule['rows'][0]['cells']['mon']['blocks'];
        $this->assertSame([], $mondayBlocks);
        $this->assertCount(1, $schedule['rows'][0]['cells']['tue']['blocks']);
        $this->assertFalse($schedule['rows'][0]['cells']['tue']['blocks'][0]['is_suggestion']);
        $this->assertSame(1, $schedule['stats']['shifts']);
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

    public function test_cover_action_options_are_the_three_cover_choices(): void
    {
        $this->assertSame([
            EmployeeScheduleShift::COVER_LEAVE_UNCOVERED => 'Leave uncovered',
            EmployeeScheduleShift::COVER_ACTION_ASSIGN_EMPLOYEE => 'Assign to an employee',
            EmployeeScheduleShift::COVER_UNASSIGNED => 'Make unassigned',
        ], EmployeeScheduleShift::coverActionOptions());
    }

    public function test_uncovered_shift_cards_include_leave_uncovered_and_unassigned_only(): void
    {
        $employee = $this->scheduleTestEmployee(10, 'emp-10', 'Aimee Fromm');
        $coverEmployee = $this->scheduleTestEmployee(11, 'emp-11', 'Sam Lee');

        $uncovered = $this->scheduleTestShift(50, 10, '2026-06-16', [
            'status' => EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
            'cover_status' => EmployeeScheduleShift::COVER_LEAVE_UNCOVERED,
            'notes' => 'Called in at 7am',
        ]);
        $uncovered->setRelation('employee', $employee);

        $unassigned = $this->scheduleTestShift(51, 10, '2026-06-17', [
            'status' => EmployeeScheduleShift::STATUS_NO_SHOW,
            'cover_status' => EmployeeScheduleShift::COVER_UNASSIGNED,
        ]);
        $unassigned->setRelation('employee', $employee);

        $assigned = $this->scheduleTestShift(52, 10, '2026-06-18', [
            'status' => EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
            'cover_status' => EmployeeScheduleShift::COVER_ASSIGNED,
            'covering_shift_id' => 99,
        ]);
        $assigned->setRelation('employee', $employee);
        $assigned->setRelation('coveringShift', tap($this->scheduleTestShift(99, 11, '2026-06-18', [
            'original_employee_id' => 10,
            'covered_from_shift_id' => 52,
        ]), static function (EmployeeScheduleShift $cover) use ($coverEmployee): void {
            $cover->setRelation('employee', $coverEmployee);
        }));

        $cards = AdminWeeklySchedule::uncoveredShiftCards(new Collection([$uncovered, $unassigned, $assigned]));

        $this->assertCount(2, $cards);
        $this->assertSame(50, $cards[0]['id']);
        $this->assertSame('Aimee Fromm', $cards[0]['employee_name']);
        $this->assertSame('Sick call out', $cards[0]['status_label']);
        $this->assertSame('Leave uncovered', $cards[0]['cover_status_label']);
        $this->assertSame('Called in at 7am', $cards[0]['notes']);
        $this->assertSame(51, $cards[1]['id']);
        $this->assertSame('No show', $cards[1]['status_label']);
        $this->assertSame('Unassigned', $cards[1]['cover_status_label']);
    }

    public function test_uncovered_schedule_places_shifts_on_the_weekly_calendar(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);
        $employee = $this->scheduleTestEmployee(10, 'emp-10', 'Aimee Fromm');

        $uncovered = $this->scheduleTestShift(50, 10, '2026-06-16', [
            'status' => EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
            'cover_status' => EmployeeScheduleShift::COVER_LEAVE_UNCOVERED,
        ]);
        $uncovered->setRelation('employee', $employee);

        $unassigned = $this->scheduleTestShift(51, 10, '2026-06-17', [
            'status' => EmployeeScheduleShift::STATUS_NO_SHOW,
            'cover_status' => EmployeeScheduleShift::COVER_UNASSIGNED,
        ]);
        $unassigned->setRelation('employee', $employee);

        $assigned = $this->scheduleTestShift(52, 10, '2026-06-18', [
            'status' => EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
            'cover_status' => EmployeeScheduleShift::COVER_ASSIGNED,
        ]);
        $assigned->setRelation('employee', $employee);

        $schedule = AdminWeeklySchedule::uncoveredSchedule(
            new Collection([$uncovered, $unassigned, $assigned]),
            $weekStart
        );

        $this->assertCount(1, $schedule['rows']);
        $this->assertSame(2, $schedule['stats']['shifts']);
        $this->assertSame('Aimee Fromm', $schedule['rows'][0]['name']);
        $this->assertSame(50, $schedule['rows'][0]['cells']['tue']['blocks'][0]['id']);
        $this->assertSame('Leave uncovered', $schedule['rows'][0]['cells']['tue']['blocks'][0]['cover_status_label']);
        $this->assertSame(51, $schedule['rows'][0]['cells']['wed']['blocks'][0]['id']);
        $this->assertSame('Unassigned', $schedule['rows'][0]['cells']['wed']['blocks'][0]['cover_status_label']);
        $this->assertSame([], $schedule['rows'][0]['cells']['thu']['blocks']);
    }

    public function test_build_schedule_shows_original_employee_on_covering_shift(): void
    {
        $weekStart = Carbon::parse('2026-06-15', 'Australia/Sydney')->startOfWeek(Carbon::MONDAY);
        $original = $this->scheduleTestEmployee(10, 'emp-10', 'Aimee Fromm');
        $coverEmployee = $this->scheduleTestEmployee(11, 'emp-11', 'Sam Lee');

        $originalShift = $this->scheduleTestShift(50, 10, '2026-06-16', [
            'status' => EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
            'cover_status' => EmployeeScheduleShift::COVER_ASSIGNED,
            'covering_shift_id' => 51,
        ]);
        $originalShift->setRelation('coveringShift', tap($this->scheduleTestShift(51, 11, '2026-06-16', [
            'original_employee_id' => 10,
            'covered_from_shift_id' => 50,
        ]), static function (EmployeeScheduleShift $cover) use ($coverEmployee): void {
            $cover->setRelation('employee', $coverEmployee);
        }));

        $coveringShift = $this->scheduleTestShift(51, 11, '2026-06-16', [
            'original_employee_id' => 10,
            'covered_from_shift_id' => 50,
        ]);
        $coveringShift->setRelation('originalEmployee', $original);
        $coveringShift->setRelation('coveredFromShift', $originalShift);

        $schedule = AdminWeeklySchedule::buildSchedule(
            new Collection([$original, $coverEmployee]),
            $weekStart,
            new Collection([$originalShift, $coveringShift])
        );

        $originalBlock = $schedule['rows'][0]['cells']['tue']['blocks'][0];
        $this->assertSame('sick_call_out', $originalBlock['status']);
        $this->assertSame('Covered', $originalBlock['cover_status_label']);
        $this->assertSame('Sam Lee', $originalBlock['covering_employee_name']);

        $coverBlock = $schedule['rows'][1]['cells']['tue']['blocks'][0];
        $this->assertTrue($coverBlock['is_cover_shift']);
        $this->assertSame('Aimee Fromm', $coverBlock['original_employee_name']);
        $this->assertSame('Sick call out', $coverBlock['original_status_label']);
    }

    public function test_format_recurrence_label_and_payload_attributes(): void
    {
        $this->assertSame('This date only', AdminWeeklySchedule::formatRecurrenceLabel('never'));
        $this->assertSame(
            'Every week · Tue · until 7 Jul 2026',
            AdminWeeklySchedule::formatRecurrenceLabel('every_week', ['tue'], '2026-07-07')
        );

        $attrs = AdminWeeklySchedule::recurrenceAttributesFromPayload([
            'recurrence' => 'every_2_weeks',
            'scheduled_date' => '2026-06-16',
            'recurrence_until' => '2026-08-01',
            'shift_days' => ['tue', 'thu'],
        ], 'series-1');

        $this->assertSame('series-1', $attrs['recurrence_series_id']);
        $this->assertSame('every_2_weeks', $attrs['recurrence_mode']);
        $this->assertSame('2026-06-16', $attrs['recurrence_starts']);
        $this->assertSame('2026-08-01', $attrs['recurrence_until']);
        $this->assertSame(['tue', 'thu'], $attrs['recurrence_days']);

        $cleared = AdminWeeklySchedule::recurrenceAttributesFromPayload([
            'recurrence' => 'never',
            'scheduled_date' => '2026-06-16',
        ], 'series-1');
        $this->assertNull($cleared['recurrence_series_id']);
        $this->assertNull($cleared['recurrence_mode']);
    }

    public function test_plan_recurrence_edit_creates_updates_and_skips_correctly(): void
    {
        $dates = AdminWeeklySchedule::recurrenceDates('2026-06-16', 'every_week', ['tue'], '2026-07-07');
        $this->assertSame(
            ['2026-06-16', '2026-06-23', '2026-06-30', '2026-07-07'],
            $dates
        );

        $plan = AdminWeeklySchedule::planRecurrenceEditActions('2026-06-16', $dates, [
            '2026-06-23' => [
                ['id' => 10, 'entry_type' => EmployeeScheduleShift::TYPE_SHIFT, 'matches_series' => true],
            ],
            '2026-06-30' => [
                ['id' => 11, 'entry_type' => EmployeeScheduleShift::TYPE_TIME_OFF, 'matches_series' => false],
            ],
            '2026-07-07' => [
                ['id' => 12, 'entry_type' => EmployeeScheduleShift::TYPE_SHIFT, 'matches_series' => false],
            ],
        ]);

        $this->assertSame([
            ['date' => '2026-06-23', 'action' => 'update', 'entry_id' => 10],
            ['date' => '2026-06-30', 'action' => 'skip', 'entry_id' => null],
            ['date' => '2026-07-07', 'action' => 'skip', 'entry_id' => null],
        ], $plan);

        $createPlan = AdminWeeklySchedule::planRecurrenceEditActions('2026-06-16', $dates, []);
        $this->assertSame([
            ['date' => '2026-06-23', 'action' => 'create', 'entry_id' => null],
            ['date' => '2026-06-30', 'action' => 'create', 'entry_id' => null],
            ['date' => '2026-07-07', 'action' => 'create', 'entry_id' => null],
        ], $createPlan);
    }

    public function test_plan_recurrence_edit_from_series_start_covers_earlier_dates(): void
    {
        $dates = AdminWeeklySchedule::recurrenceDates('2026-09-10', 'every_week', ['thu', 'fri', 'sun', 'tue', 'wed'], '2026-09-17');
        $this->assertSame(
            ['2026-09-10', '2026-09-11', '2026-09-13', '2026-09-15', '2026-09-16', '2026-09-17'],
            $dates
        );

        $plan = AdminWeeklySchedule::planRecurrenceEditActions('2026-09-15', $dates, [
            '2026-09-10' => [
                ['id' => 1, 'entry_type' => EmployeeScheduleShift::TYPE_SHIFT, 'matches_series' => true],
            ],
            '2026-09-11' => [
                ['id' => 2, 'entry_type' => EmployeeScheduleShift::TYPE_SHIFT, 'matches_series' => true],
            ],
        ]);

        $byDate = collect($plan)->keyBy('date');
        $this->assertSame('update', $byDate['2026-09-10']['action']);
        $this->assertSame('update', $byDate['2026-09-11']['action']);
        $this->assertSame('create', $byDate['2026-09-13']['action']);
        $this->assertSame('create', $byDate['2026-09-16']['action']);
        $this->assertArrayNotHasKey('2026-09-15', $byDate->all());
    }

    public function test_series_dates_to_remove_drops_unchecked_weekdays(): void
    {
        $existing = ['2026-09-10', '2026-09-11', '2026-09-13', '2026-09-15', '2026-09-16'];
        $newDates = AdminWeeklySchedule::recurrenceDates(
            '2026-09-10',
            'every_week',
            ['thu'],
            '2026-09-17'
        );

        $this->assertSame(['2026-09-10', '2026-09-17'], $newDates);
        $this->assertSame(
            ['2026-09-11', '2026-09-13', '2026-09-15', '2026-09-16'],
            AdminWeeklySchedule::seriesDatesToRemove($existing, $newDates)
        );
        $this->assertContains('2026-09-15', AdminWeeklySchedule::seriesDatesToRemove($existing, $newDates));
    }

    private function scheduleTestEmployee(int $id, string $publicId, string $name): Employee
    {
        $employee = new Employee([
            'public_id' => $publicId,
            'full_legal_name' => $name,
            'email' => strtolower(str_replace(' ', '.', $name)).'@example.com',
        ]);
        $employee->id = $id;
        $employee->setRelation('assignedDepartment', null);
        $employee->setRelation('workLocation', null);
        $employee->setRelation('assignedJobTitle', null);
        $employee->setRelation('assignedShift', null);
        $employee->setRelation('assignmentShifts', new Collection);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function scheduleTestShift(int $id, int $employeeId, string $date, array $attributes = []): EmployeeScheduleShift
    {
        $entry = new EmployeeScheduleShift([
            'employee_id' => $employeeId,
            'scheduled_date' => $date,
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => '09:00',
            'end_time' => '17:00',
            ...$attributes,
        ]);
        $entry->id = $id;
        $entry->setRelation('shiftTemplate', null);
        $entry->setRelation('jobTitle', null);
        $entry->setRelation('department', null);
        $entry->setRelation('workLocation', null);
        $entry->setRelation('leaveType', null);
        $entry->setRelation('leaveRecord', null);

        return $entry;
    }
}
