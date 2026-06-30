<?php

namespace Tests\Unit;

use App\Models\EmployeeTaskAssignment;
use App\Models\WorkLocation;
use App\Support\AdminEmployeeTasks;
use Tests\TestCase;

class AdminEmployeeTasksTest extends TestCase
{
    public function test_matches_role_and_shift_respects_optional_filters(): void
    {
        $this->assertTrue(AdminEmployeeTasks::matchesRoleAndShift(null, null, 5, 7));
        $this->assertTrue(AdminEmployeeTasks::matchesRoleAndShift(5, null, 5, 7));
        $this->assertTrue(AdminEmployeeTasks::matchesRoleAndShift(null, 7, 5, 7));
        $this->assertFalse(AdminEmployeeTasks::matchesRoleAndShift(99, null, 5, 7));
        $this->assertFalse(AdminEmployeeTasks::matchesRoleAndShift(null, 99, 5, 7));
    }

    public function test_assignment_payload_shapes_employee_tasks(): void
    {
        $location = new WorkLocation(['name' => 'Rose City Shopping Centre']);
        $location->id = 2;

        $assignment = new EmployeeTaskAssignment([
            'employee_id' => 10,
            'work_location_id' => 2,
            'title' => 'Cover break at register 3',
            'scheduled_date' => '2026-06-28',
            'start_time' => '12:00',
            'end_time' => '12:30',
            'is_active' => true,
        ]);
        $assignment->id = 20;
        $assignment->setRelation('workLocation', $location);
        $assignment->setRelation('jobTitle', null);
        $assignment->setRelation('shift', null);

        $assignmentPayload = AdminEmployeeTasks::assignmentToPayload($assignment);

        $this->assertSame('Cover break at register 3', $assignmentPayload['title']);
        $this->assertSame('Rose City Shopping Centre', $assignmentPayload['work_location']['name']);
        $this->assertSame('2026-06-28', $assignmentPayload['scheduled_date']);
        $this->assertSame('12:00 – 12:30', $assignmentPayload['time_range']);
        $this->assertFalse($assignmentPayload['completed']);
    }

    public function test_future_due_date_task_uses_scheduled_date_for_completion_bucket(): void
    {
        $assignment = new EmployeeTaskAssignment([
            'employee_id' => 10,
            'work_location_id' => 2,
            'title' => 'Stocktake',
            'scheduled_date' => '2026-07-01',
            'is_active' => true,
        ]);
        $assignment->id = 21;

        $this->assertSame('2026-07-01', AdminEmployeeTasks::completionDateForAssignment($assignment, '2026-06-28'));
    }

    public function test_ongoing_task_uses_view_date_for_completion_bucket(): void
    {
        $assignment = new EmployeeTaskAssignment([
            'employee_id' => 10,
            'work_location_id' => 2,
            'title' => 'Daily check',
            'is_active' => true,
        ]);
        $assignment->id = 22;

        $this->assertSame('2026-06-28', AdminEmployeeTasks::completionDateForAssignment($assignment, '2026-06-28'));
    }

    public function test_completion_for_assignment_display_falls_back_to_any_completion(): void
    {
        $assignment = new EmployeeTaskAssignment([
            'employee_id' => 10,
            'work_location_id' => 2,
            'title' => 'Daily check',
            'is_active' => true,
        ]);
        $assignment->id = 30;

        $completion = new \App\Models\EmployeeTaskCompletion([
            'employee_id' => 10,
            'employee_task_assignment_id' => 30,
            'completion_date' => '2026-06-27',
            'completed_at' => '2026-06-27 14:00:00',
        ]);
        $completion->id = 1;

        $found = AdminEmployeeTasks::completionForAssignmentDisplay(
            $assignment,
            collect([$completion]),
            '2026-06-28',
        );

        $this->assertNotNull($found);
        $this->assertSame(30, (int) $found->employee_task_assignment_id);
    }

    public function test_completion_summary_counts_completed_tasks(): void
    {
        $tasks = [
            ['completed' => true],
            ['completed' => false],
            ['completed' => true],
        ];

        $summary = AdminEmployeeTasks::completionSummary($tasks);

        $this->assertSame(3, $summary['total']);
        $this->assertSame(2, $summary['completed']);
        $this->assertSame(1, $summary['pending']);
    }
}
