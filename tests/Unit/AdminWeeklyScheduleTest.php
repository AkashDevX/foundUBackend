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
        $entry->setRelation('shiftTemplate', new Shift(['name' => 'Morning retail']));
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
        $this->assertSame('Morning retail', $tuesdayBlocks[0]['subtitle']);
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

    public function test_resolve_week_start_normalizes_to_monday(): void
    {
        $weekStart = AdminWeeklySchedule::resolveWeekStart('2026-06-18');

        $this->assertSame(Carbon::MONDAY, $weekStart->dayOfWeek);
        $this->assertSame('2026-06-15', $weekStart->toDateString());
    }
}
