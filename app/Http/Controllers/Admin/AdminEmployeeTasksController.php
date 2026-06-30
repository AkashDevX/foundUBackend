<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeTaskAssignment;
use App\Models\OrganizationPortalUser;
use App\Models\WorkLocation;
use App\Support\AdminEmployeeTasks;
use App\Support\AdminWeeklySchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminEmployeeTasksController extends Controller
{
    public function index(Request $request): View
    {
        $context = $this->pageContext($request);

        return view('admin.employees-tasks', $context);
    }

    public function store(Request $request): RedirectResponse
    {
        $context = $this->pageContext($request);
        $conn = $context['conn'];
        $data = $this->validatedAssignmentPayload($request, $conn);

        /** @var Employee $employee */
        $employee = Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        $title = trim((string) $data['title']);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Enter a task title.',
            ]);
        }

        $workLocationId = $employee->work_location_id;
        if ($workLocationId === null) {
            throw ValidationException::withMessages([
                'employee_public_id' => 'Assign a work location to this employee before adding tasks.',
            ]);
        }

        EmployeeTaskAssignment::on($conn)->create([
            'employee_id' => $employee->id,
            'work_location_id' => $workLocationId,
            'title' => $title,
            'description' => null,
            'job_title_id' => null,
            'shift_id' => null,
            'scheduled_date' => isset($data['scheduled_date']) && $data['scheduled_date'] !== '' ? $data['scheduled_date'] : null,
            'start_time' => null,
            'end_time' => null,
            'notes' => null,
            'created_by' => $portalUser->name,
            'is_active' => true,
        ]);

        return $this->redirectBack($request, 'Task assigned to employee.');
    }

    public function update(Request $request, int $taskAssignment): RedirectResponse
    {
        $context = $this->pageContext($request);
        $conn = $context['conn'];
        $data = $this->validatedAssignmentPayload($request, $conn, updating: true);

        /** @var EmployeeTaskAssignment $assignment */
        $assignment = EmployeeTaskAssignment::on($conn)->findOrFail($taskAssignment);

        /** @var Employee $employee */
        $employee = Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        $title = trim((string) $data['title']);
        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Task title is required.',
            ]);
        }

        $assignment->fill([
            'employee_id' => $employee->id,
            'work_location_id' => $data['work_location_id'],
            'title' => $title,
            'description' => $this->nullableString($data['description'] ?? null),
            'job_title_id' => $data['job_title_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'scheduled_date' => $data['scheduled_date'] ?? null,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'notes' => $this->nullableString($data['notes'] ?? null, 500),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);
        $assignment->save();

        return $this->redirectBack($request, 'Employee task updated.');
    }

    public function destroy(Request $request, int $taskAssignment): RedirectResponse
    {
        $context = $this->pageContext($request);
        $conn = $context['conn'];

        $assignment = EmployeeTaskAssignment::on($conn)->findOrFail($taskAssignment);
        $assignment->delete();

        return $this->redirectBack($request, 'Employee task removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function pageContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $date = AdminEmployeeTasks::resolveDate(
            is_string($request->query('date')) ? $request->query('date') : null
        );

        $employees = $this->filteredEmployeesQuery($request, $conn)
            ->with(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift'])
            ->get();

        $assignmentQuery = EmployeeTaskAssignment::on($conn)
            ->with(['workLocation', 'jobTitle', 'shift'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('is_active', true)
            ->orderByRaw('scheduled_date IS NULL DESC')
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('title');

        $assignmentsByEmployee = $assignmentQuery->get()->groupBy('employee_id');

        $rows = [];
        foreach ($employees as $employee) {
            $employeeAssignments = $assignmentsByEmployee->get($employee->id, collect());
            $completions = AdminEmployeeTasks::completionsForEmployee($employee);

            $assignedTaskPayload = $employeeAssignments
                ->map(static fn (EmployeeTaskAssignment $assignment): array => AdminEmployeeTasks::assignmentToPayload(
                    $assignment,
                    AdminEmployeeTasks::completionForAssignmentDisplay($assignment, $completions, $date),
                ))
                ->all();

            $rows[] = [
                'employee' => $employee,
                'name' => AdminWeeklySchedule::employeeDisplayName($employee),
                'initials' => AdminWeeklySchedule::employeeInitials($employee),
                'job_title' => AdminWeeklySchedule::employeeJobTitle($employee),
                'assigned_tasks' => $assignedTaskPayload,
                'completion_summary' => AdminEmployeeTasks::completionSummary($assignedTaskPayload),
            ];
        }

        $workLocationId = $request->query('work_location_id');
        $employeePublicId = $request->query('employee');

        $filterParams = array_filter([
            'work_location_id' => is_string($workLocationId) && $workLocationId !== '' ? $workLocationId : null,
            'employee' => is_string($employeePublicId) && $employeePublicId !== '' ? $employeePublicId : null,
            'date' => $date,
        ], static fn ($value) => $value !== null && $value !== '');

        return [
            'company' => $company,
            'conn' => $conn,
            'date' => $date,
            'taskRows' => $rows,
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::on($conn)
                ->where('employment_status', 'active')
                ->orderBy('full_legal_name')
                ->get(['id', 'public_id', 'full_legal_name', 'email', 'work_location_id']),
            'filters' => [
                'work_location_id' => is_string($workLocationId) ? $workLocationId : '',
                'employee' => is_string($employeePublicId) ? $employeePublicId : '',
            ],
            'redirectQuery' => $filterParams,
        ];
    }

    private function filteredEmployeesQuery(Request $request, string $conn)
    {
        $workLocationId = $request->query('work_location_id');
        $employeePublicId = $request->query('employee');

        $employeesQuery = Employee::on($conn)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name');

        if (is_string($workLocationId) && $workLocationId !== '' && ctype_digit($workLocationId)) {
            $employeesQuery->where('work_location_id', (int) $workLocationId);
        }

        if (is_string($employeePublicId) && $employeePublicId !== '') {
            $employeesQuery->where('public_id', $employeePublicId);
        }

        return $employeesQuery;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedAssignmentPayload(Request $request, string $conn, bool $updating = false): array
    {
        $rules = [
            'employee_public_id' => ['required', 'string'],
            'title' => ['required', 'string', 'max:200'],
            'scheduled_date' => ['nullable', 'date'],
        ];

        if ($updating) {
            $rules['work_location_id'] = ['required', 'integer'];
            $rules['is_active'] = ['nullable', 'boolean'];
        }

        $data = $request->validate($rules);

        Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        if ($updating) {
            $this->assertBelongsToTenant($conn, 'work_locations', (int) $data['work_location_id']);
        }

        return $data;
    }

    private function assertBelongsToTenant(string $connection, string $table, ?int $id): void
    {
        if ($id === null) {
            return;
        }

        $exists = DB::connection($connection)->table($table)->where('id', $id)->exists();
        if (! $exists) {
            throw ValidationException::withMessages([
                'task' => 'The selected option is invalid for this organization.',
            ]);
        }
    }

    private function nullableString(?string $value, int $max = 5000): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        return mb_substr($trimmed, 0, $max);
    }

    private function redirectBack(Request $request, string $message): RedirectResponse
    {
        $redirect = $request->input('redirect', []);
        if (! is_array($redirect)) {
            $redirect = [];
        }

        $params = array_filter([
            'date' => is_string($redirect['date'] ?? null) && $redirect['date'] !== '' ? $redirect['date'] : null,
            'work_location_id' => is_string($redirect['work_location_id'] ?? null) && $redirect['work_location_id'] !== '' ? $redirect['work_location_id'] : null,
            'employee' => is_string($redirect['employee'] ?? null) && $redirect['employee'] !== '' ? $redirect['employee'] : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('admin.employees.tasks', $params)
            ->with('status', $message);
    }
}
