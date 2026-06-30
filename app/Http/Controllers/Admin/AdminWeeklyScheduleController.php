<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Support\AdminWeeklySchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminWeeklyScheduleController extends Controller
{
    public function index(Request $request): View
    {
        $context = $this->scheduleContext($request);

        return view('admin.employees-weekly-schedule', $context);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];
        $data = $this->validatedShiftPayload($request, $conn);

        /** @var Employee $employee */
        $employee = Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->applyEmployeeShiftDefaults($data, $employee, $conn);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);
        } else {
            $this->clearShiftsForDay($conn, (int) $employee->id, $data['scheduled_date']);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);
        }

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');

        EmployeeScheduleShift::on($conn)->create($this->scheduleEntryAttributes($data, $employee, $portalUser->name));

        $message = $data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF
            ? 'Day off saved to the weekly schedule.'
            : 'Shift saved to the weekly schedule.';

        return $this->redirectBack($request, $message);
    }

    public function updateShift(Request $request, int $scheduleShift): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->findOrFail($scheduleShift);

        $data = $this->validatedShiftPayload($request, $conn);

        /** @var Employee $employee */
        $employee = Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->applyEmployeeShiftDefaults($data, $employee, $conn);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);
        } else {
            $this->clearShiftsForDay($conn, (int) $employee->id, $data['scheduled_date'], exceptId: (int) $entry->id);
        }

        $entry->fill($this->scheduleEntryAttributes($data, $employee));
        $entry->save();

        $message = $data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF
            ? 'Day off updated.'
            : 'Shift updated.';

        return $this->redirectBack($request, $message);
    }

    public function destroyShift(Request $request, int $scheduleShift): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];

        $entry = EmployeeScheduleShift::on($conn)->findOrFail($scheduleShift);
        $wasTimeOff = $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF;
        $entry->delete();

        return $this->redirectBack($request, $wasTimeOff
            ? 'Day off removed from the schedule.'
            : 'Shift removed from the schedule.');
    }

    public function fillFromAssignments(Request $request): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];
        $weekStart = $context['weekStart'];

        $employees = $this->filteredEmployeesQuery($request, $conn)->with(['assignedShift'])->get();
        $weekEnd = $weekStart->copy()->addDays(6)->toDateString();

        $existingEntries = EmployeeScheduleShift::on($conn)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd])
            ->get();

        $created = AdminWeeklySchedule::fillWeekFromAssignments($conn, $employees, $weekStart, $existingEntries);

        $message = $created > 0
            ? sprintf('Added %d shift block(s) from work assignments.', $created)
            : 'No empty days were found to fill from assignments.';

        return $this->redirectBack($request, $message);
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleContext(Request $request): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $weekStart = AdminWeeklySchedule::resolveWeekStart($request->query('week'));
        $weekEnd = $weekStart->copy()->addDays(6);

        $employees = $this->filteredEmployeesQuery($request, $conn)
            ->with(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift'])
            ->get();

        $scheduleEntries = EmployeeScheduleShift::on($conn)
            ->with(['shiftTemplate', 'jobTitle', 'department', 'workLocation'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $schedule = AdminWeeklySchedule::buildSchedule($employees, $weekStart, $scheduleEntries);

        $departmentId = $request->query('department_id');
        $workLocationId = $request->query('work_location_id');
        $employeePublicId = $request->query('employee');

        $filterParams = array_filter([
            'department_id' => is_string($departmentId) && $departmentId !== '' ? $departmentId : null,
            'work_location_id' => is_string($workLocationId) && $workLocationId !== '' ? $workLocationId : null,
            'employee' => is_string($employeePublicId) && $employeePublicId !== '' ? $employeePublicId : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $weekLink = static function (?string $week) use ($filterParams): string {
            return route('admin.employees.weekly-schedule', array_filter([
                ...$filterParams,
                'week' => $week,
            ]));
        };

        return [
            'company' => $company,
            'conn' => $conn,
            'weekStart' => $weekStart,
            'weekLabel' => AdminWeeklySchedule::formatWeekLabel($weekStart),
            'weekDays' => $schedule['days'],
            'scheduleRows' => $schedule['rows'],
            'scheduleStats' => $schedule['stats'],
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'shiftTemplates' => Shift::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::on($conn)
                ->where('employment_status', 'active')
                ->orderBy('full_legal_name')
                ->get(['id', 'public_id', 'full_legal_name', 'email', 'job_title_id', 'department_id', 'work_location_id', 'shift_id']),
            'filters' => [
                'department_id' => is_string($departmentId) ? $departmentId : '',
                'work_location_id' => is_string($workLocationId) ? $workLocationId : '',
                'employee' => is_string($employeePublicId) ? $employeePublicId : '',
            ],
            'weekLinks' => [
                'prev' => $weekLink($weekStart->copy()->subWeek()->toDateString()),
                'next' => $weekLink($weekStart->copy()->addWeek()->toDateString()),
                'today' => $weekLink(AdminWeeklySchedule::resolveWeekStart(null)->toDateString()),
            ],
            'redirectQuery' => array_filter([
                'week' => $weekStart->toDateString(),
                ...$filterParams,
            ]),
        ];
    }

    private function filteredEmployeesQuery(Request $request, string $conn)
    {
        $departmentId = $request->query('department_id');
        $workLocationId = $request->query('work_location_id');
        $employeePublicId = $request->query('employee');

        $employeesQuery = Employee::on($conn)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name');

        if (is_string($departmentId) && $departmentId !== '' && ctype_digit($departmentId)) {
            $employeesQuery->where('department_id', (int) $departmentId);
        }

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
    private function validatedShiftPayload(Request $request, string $conn): array
    {
        $data = $request->validate([
            'employee_public_id' => ['required', 'string'],
            'scheduled_date' => ['required', 'date'],
            'entry_type' => ['required', Rule::in([EmployeeScheduleShift::TYPE_SHIFT, EmployeeScheduleShift::TYPE_TIME_OFF])],
            'shift_id' => ['nullable', 'integer', 'required_if:entry_type,'.EmployeeScheduleShift::TYPE_SHIFT],
            'work_location_id' => ['nullable', 'integer', 'required_if:entry_type,'.EmployeeScheduleShift::TYPE_SHIFT],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $this->assertBelongsToTenant($conn, 'shifts', $data['shift_id'] ?? null);
            $this->assertBelongsToTenant($conn, 'work_locations', $data['work_location_id'] ?? null);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function scheduleEntryAttributes(array $data, Employee $employee, ?string $createdBy = null): array
    {
        $isTimeOff = $data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF;

        $attributes = [
            'employee_id' => $employee->id,
            'scheduled_date' => $data['scheduled_date'],
            'entry_type' => $data['entry_type'],
            'job_title_id' => $employee->job_title_id,
            'department_id' => $employee->department_id,
        ];

        if ($isTimeOff) {
            $attributes = [
                ...$attributes,
                'start_time' => null,
                'end_time' => null,
                'shift_id' => null,
                'work_location_id' => null,
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            ];
        } else {
            $attributes = [
                ...$attributes,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'shift_id' => $data['shift_id'],
                'work_location_id' => $data['work_location_id'],
                'notes' => null,
            ];
        }

        if ($createdBy !== null) {
            $attributes['created_by'] = $createdBy;
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function applyEmployeeShiftDefaults(array $data, Employee $employee, string $conn): array
    {
        /** @var Shift $shift */
        $shift = Shift::on($conn)->findOrFail($data['shift_id']);

        if ($shift->start_time instanceof \Carbon\CarbonInterface) {
            $data['start_time'] = $shift->start_time->format('H:i');
        }
        if ($shift->end_time instanceof \Carbon\CarbonInterface) {
            $data['end_time'] = $shift->end_time->format('H:i');
        }

        return $data;
    }

    private function clearTimeOffForDay(string $conn, int $employeeId, string $scheduledDate): void
    {
        EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employeeId)
            ->where('scheduled_date', $scheduledDate)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF)
            ->delete();
    }

    private function clearShiftsForDay(string $conn, int $employeeId, string $scheduledDate, ?int $exceptId = null): void
    {
        $query = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employeeId)
            ->where('scheduled_date', $scheduledDate)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $query->delete();
    }

    private function assertBelongsToTenant(string $connection, string $table, ?int $id): void
    {
        if ($id === null) {
            return;
        }

        $exists = DB::connection($connection)
            ->table($table)
            ->where('id', $id)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                'shift' => 'The selected option is invalid for this organization.',
            ]);
        }
    }

    private function redirectBack(Request $request, string $message): RedirectResponse
    {
        $redirect = $request->input('redirect', []);
        if (! is_array($redirect)) {
            $redirect = [];
        }

        $params = array_filter([
            'week' => is_string($redirect['week'] ?? null) && $redirect['week'] !== '' ? $redirect['week'] : null,
            'department_id' => is_string($redirect['department_id'] ?? null) && $redirect['department_id'] !== '' ? $redirect['department_id'] : null,
            'work_location_id' => is_string($redirect['work_location_id'] ?? null) && $redirect['work_location_id'] !== '' ? $redirect['work_location_id'] : null,
            'employee' => is_string($redirect['employee'] ?? null) && $redirect['employee'] !== '' ? $redirect['employee'] : null,
        ], static fn ($value) => $value !== null && $value !== '');

        return redirect()
            ->route('admin.employees.weekly-schedule', $params)
            ->with('status', $message);
    }
}
