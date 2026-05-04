<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\OrganizationPortalUser;
use App\Models\RegistrationPicklistItem;
use App\Models\Shift;
use App\Models\WorkLocation;
use App\Support\AdminWeeklyAvailability;
use App\Support\RegistrationDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Lists registration submissions for the signed-in organization only (tenant DB).
     */
    public function index(Request $request): View
    {
        $statusFilter = $request->query('status'); // pending | active | null (all)

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();

        $rows = [];
        $tenantError = null;
        $statsTotal = 0;
        $statsPending = 0;
        $statsActive = 0;
        $statsDeclined = 0;

        try {
            $conn = $company->tenant_connection;
            $statsTotal = Employee::on($conn)->count();
            $statsPending = Employee::on($conn)->where('employment_status', 'pending')->count();
            $statsActive = Employee::on($conn)->where('employment_status', 'active')->count();
            $statsDeclined = Employee::on($conn)->whereIn('employment_status', ['declined', 'rejected'])->count();

            $query = Employee::on($conn)->orderByDesc('created_at');

            if ($statusFilter !== null && $statusFilter !== '') {
                $query->where('employment_status', $statusFilter);
            }

            foreach ($query->get() as $employee) {
                $rows[] = ['employee' => $employee];
            }
        } catch (\Throwable $e) {
            $tenantError = $e->getMessage();
        }

        return view('admin.dashboard', [
            'currentCompany' => $company,
            'rows' => $rows,
            'tenantError' => $tenantError,
            'statusFilter' => $statusFilter,
            'statsTotal' => $statsTotal,
            'statsPending' => $statsPending,
            'statsActive' => $statsActive,
            'statsDeclined' => $statsDeclined,
        ]);
    }

    /**
     * Full submitted profile for one employee (same organization as portal session only).
     */
    public function show(Request $request, string $companySlug, string $publicId): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();

        abort_unless($sessionCompany->slug === $companySlug, 403);

        $conn = $sessionCompany->tenant_connection;

        RegistrationDisplay::resetDatabaseRowCache();

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->firstOrFail();

        $employee->load(['assignedDepartment', 'workLocation', 'assignedShift']);

        $departments = Department::on($conn)->where('is_active', true)->orderBy('name')->get();
        $workLocations = WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get();
        $shifts = Shift::on($conn)->where('is_active', true)->orderBy('name')->get();

        $registrationPicklists = RegistrationPicklistItem::query()
            ->where('is_active', true)
            ->orderBy('picklist_key')
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('picklist_key');

        $availabilityCalendar = AdminWeeklyAvailability::calendarState($employee->weekly_availability_json);

        $registrationDateInputs = [];
        foreach (RegistrationDisplay::adminProfileDateMetadataKeys() as $column => $metaKeys) {
            $registrationDateInputs[$column] = RegistrationDisplay::adminDateInputValue($request, $employee, $column, $metaKeys);
        }
        $registrationDateInputs['assignment_effective_from'] = RegistrationDisplay::adminAssignmentEffectiveInput($request, $employee);
        $registrationDateInputs = RegistrationDisplay::mergeRegistrationDatesFromDatabase($conn, $publicId, $registrationDateInputs);

        return view('admin.registration-show', [
            'company' => $sessionCompany,
            'employee' => $employee,
            'departments' => $departments,
            'workLocations' => $workLocations,
            'shifts' => $shifts,
            'registrationPicklists' => $registrationPicklists,
            'availabilityCalendar' => $availabilityCalendar,
            'registrationDateInputs' => $registrationDateInputs,
        ]);
    }
}
