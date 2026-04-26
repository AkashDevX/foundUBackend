<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminEmployeeAssignmentController extends Controller
{
    public function index(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $employeesQuery = Employee::on($conn)
            ->with(['assignedDepartment', 'workLocation', 'assignedShift'])
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name');

        $employees = $employeesQuery->get();
        $departments = Department::on($conn)->where('is_active', true)->orderBy('name')->get();
        $workLocations = WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get();
        $shifts = Shift::on($conn)->where('is_active', true)->orderBy('name')->get();

        return view('admin.employees', [
            'company' => $company,
            'employees' => $employees,
            'departments' => $departments,
            'workLocations' => $workLocations,
            'shifts' => $shifts,
        ]);
    }

    public function update(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();
        abort_unless($sessionCompany->slug === $companySlug, 403);

        $conn = $sessionCompany->tenant_connection;

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $this->updateAssignmentForEmployee($request, $employee, $conn);

        return redirect()
            ->route('admin.registrations.show', ['companySlug' => $companySlug, 'publicId' => $publicId])
            ->with('status', 'Work assignment updated.');
    }

    public function updateFromList(Request $request, string $publicId): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $this->updateAssignmentForEmployee($request, $employee, $conn);

        return redirect()
            ->route('admin.employees')
            ->with('status', 'Work assignment updated for '.$employee->full_legal_name.'.');
    }

    private function updateAssignmentForEmployee(Request $request, Employee $employee, string $conn): void
    {
        $nullableInt = static function (mixed $v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }

            return null;
        };

        $request->merge([
            'department_id' => $nullableInt($request->input('department_id')),
            'work_location_id' => $nullableInt($request->input('work_location_id')),
            'shift_id' => $nullableInt($request->input('shift_id')),
            'assignment_effective_from' => $request->filled('assignment_effective_from') ? $request->input('assignment_effective_from') : null,
        ]);

        if (($employee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'assignment' => 'Work assignment can only be set for active employees.',
            ]);
        }

        $data = $request->validate([
            'department_id' => ['nullable', 'integer'],
            'work_location_id' => ['nullable', 'integer'],
            'shift_id' => ['nullable', 'integer'],
            'assignment_effective_from' => ['nullable', 'date'],
            'assignment_notes' => ['nullable', 'string', 'max:5000'],
        ]);

        $this->assertBelongsToTenant($conn, 'departments', $data['department_id'] ?? null);
        $this->assertBelongsToTenant($conn, 'work_locations', $data['work_location_id'] ?? null);
        $this->assertBelongsToTenant($conn, 'shifts', $data['shift_id'] ?? null);

        $employee->forceFill([
            'department_id' => $data['department_id'] ?? null,
            'work_location_id' => $data['work_location_id'] ?? null,
            'shift_id' => $data['shift_id'] ?? null,
            'assignment_effective_from' => $data['assignment_effective_from'] ?? null,
            'assignment_notes' => $data['assignment_notes'] ?? null,
        ])->save();
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
                'assignment' => 'The selected option is invalid for this organization.',
            ]);
        }
    }
}
