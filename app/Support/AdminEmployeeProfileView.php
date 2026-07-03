<?php

namespace App\Support;

use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\RegistrationPicklistItem;
use App\Models\Shift;
use App\Models\WorkLocation;
use Illuminate\Http\Request;

final class AdminEmployeeProfileView
{
    /**
     * @return array{
     *     company: Company,
     *     employee: Employee,
     *     departments: \Illuminate\Support\Collection,
     *     jobTitles: \Illuminate\Support\Collection,
     *     workLocations: \Illuminate\Support\Collection,
     *     shifts: \Illuminate\Support\Collection,
     *     registrationPicklists: \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<int, RegistrationPicklistItem>>,
     *     weeklyGrid: array<string, array{morning: bool, evening: bool}>,
     *     registrationDateInputs: array<string, string>,
     *     registrationDateFormats: array<string, string>,
     * }
     */
    public static function viewData(Request $request, Company $company, Employee $employee): array
    {
        $conn = $company->tenant_connection;
        $publicId = $employee->public_id;

        RegistrationDisplay::resetDatabaseRowCache();

        $employee->load(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift', 'leaveRecords']);

        $registrationPicklists = RegistrationPicklistItem::query()
            ->where('is_active', true)
            ->orderBy('picklist_key')
            ->orderBy('sort_order')
            ->orderBy('value')
            ->get()
            ->groupBy('picklist_key');

        $weeklyGrid = AdminWeeklyAvailability::mobileGridStateForEmployee(
            $employee->weekly_availability_json,
            $employee->weekly_availability_summary
        );

        $registrationDateInputs = [];
        $registrationDateFormats = [];
        foreach (RegistrationDisplay::adminProfileDateMetadataKeys() as $column => $metaKeys) {
            $registrationDateInputs[$column] = RegistrationDisplay::adminDateInputValue($request, $employee, $column, $metaKeys);
            $registrationDateFormats[$column] = RegistrationDisplay::adminDateStorageFormat($request, $employee, $column, $metaKeys);
        }
        $registrationDateInputs['assignment_effective_from'] = RegistrationDisplay::adminAssignmentEffectiveInput($request, $employee);
        $registrationDateInputs = RegistrationDisplay::mergeRegistrationDatesFromDatabase($conn, $publicId, $registrationDateInputs);
        $registrationDateFormats = RegistrationDisplay::mergeRegistrationDateFormatsFromDatabase($conn, $publicId, $registrationDateFormats);

        return [
            'company' => $company,
            'employee' => $employee,
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'jobTitles' => JobTitle::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'registrationPicklists' => $registrationPicklists,
            'weeklyGrid' => $weeklyGrid,
            'registrationDateInputs' => $registrationDateInputs,
            'registrationDateFormats' => $registrationDateFormats,
        ];
    }
}
