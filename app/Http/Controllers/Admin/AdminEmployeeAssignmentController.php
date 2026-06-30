<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobTitle;
use App\Models\OrganizationPortalUser;
use App\Models\RegistrationPicklistItem;
use App\Models\Shift;
use App\Models\TimesheetApproval;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Services\RegistrationDocumentStorage;
use App\Support\AdminTimesheetApproval;
use App\Support\AdminWeeklyAvailability;
use App\Support\DisplayTimezone;
use App\Support\FoundUProfileMapper;
use App\Support\RegistrationDisplay;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminEmployeeAssignmentController extends Controller
{
    public function assignments(Request $request): View
    {
        $data = $this->employeePageData($request, loadTimeClockEntries: false);

        return view('admin.employees-assignments', $data);
    }

    public function timeClock(Request $request): View
    {
        $timesheetStatusFilter = $request->query('timesheet_status', 'pending');
        if (! in_array($timesheetStatusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
            $timesheetStatusFilter = 'pending';
        }

        $data = $this->employeePageData($request, loadTimeClockEntries: true, loadTimesheetHistory: true);

        $selectedPublicId = $request->query('employee');
        $selectedEmployee = null;
        if (is_string($selectedPublicId) && $selectedPublicId !== '') {
            $selectedEmployee = $data['employees']->firstWhere('public_id', $selectedPublicId);
        }

        $eventFilter = $request->query('event', 'all');
        if (! in_array($eventFilter, ['all', 'clock_in', 'clock_out'], true)) {
            $eventFilter = 'all';
        }

        $employeesForTimesheets = $selectedEmployee !== null
            ? collect([$selectedEmployee])
            : $data['employees'];

        $timesheetRows = AdminTimesheetApproval::buildRows(
            $employeesForTimesheets,
            $data['timesheetApprovals'],
            $timesheetStatusFilter === 'all' ? null : $timesheetStatusFilter
        );

        $data['selectedEmployee'] = $selectedEmployee;
        $data['eventFilter'] = $eventFilter;
        $data['timesheetStatusFilter'] = $timesheetStatusFilter;
        $data['timesheetRows'] = $timesheetRows;

        return view('admin.employees-time-clock', $data);
    }

    public function approveTimesheet(Request $request): RedirectResponse
    {
        return $this->reviewTimesheet($request, TimesheetApproval::STATUS_APPROVED, 'Timesheet approved.');
    }

    public function rejectTimesheet(Request $request): RedirectResponse
    {
        return $this->reviewTimesheet($request, TimesheetApproval::STATUS_REJECTED, 'Timesheet rejected.');
    }

    private function reviewTimesheet(Request $request, string $status, string $flashMessage): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'employee' => ['required', 'string', 'max:64'],
            'week_start' => ['required', 'date'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $data['employee'])
            ->where('employment_status', 'active')
            ->firstOrFail();

        $weekStart = AdminTimesheetApproval::normalizeWeekStart($data['week_start']);
        $weekEnd = AdminTimesheetApproval::weekEndForStart($weekStart);

        $since = DisplayTimezone::now()->subWeeks(12)->startOfWeek(\Carbon\Carbon::MONDAY)->utc();
        $entries = TimeClockEntry::on($conn)
            ->where('employee_id', $employee->id)
            ->where('clocked_at', '>=', $since)
            ->orderBy('clocked_at')
            ->get();

        $weekEntries = AdminTimesheetApproval::groupEntriesByWeek($entries)[$weekStart] ?? collect();
        if ($weekEntries->isEmpty()) {
            throw ValidationException::withMessages([
                'week_start' => 'No clock activity found for that week.',
            ]);
        }

        $summary = \App\Support\AdminTimeClockDisplay::summarizeWorkSessions($weekEntries);

        TimesheetApproval::on($conn)->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'week_start' => $weekStart,
            ],
            [
                'week_end' => $weekEnd,
                'total_seconds' => (int) $summary['total_seconds'],
                'completed_sessions' => (int) $summary['completed_sessions'],
                'status' => $status,
                'reviewed_by' => $portalUser->name ?: $portalUser->email,
                'reviewed_at' => now('UTC'),
                'review_notes' => $data['review_notes'] ?? null,
            ]
        );

        return redirect()
            ->route('admin.employees.time-clock', array_filter([
                'employee' => $employee->public_id,
                'timesheet_status' => $request->input('timesheet_status', 'pending'),
            ]))
            ->with('status', $flashMessage);
    }

    /**
     * @return array{
     *     company: \App\Models\Company,
     *     employees: \Illuminate\Support\Collection<int, Employee>,
     *     departments: \Illuminate\Support\Collection,
     *     workLocations: \Illuminate\Support\Collection,
     *     shifts: \Illuminate\Support\Collection,
     *     timesheetApprovals: \Illuminate\Support\Collection<int, TimesheetApproval>,
     * }
     */
    private function employeePageData(Request $request, bool $loadTimeClockEntries, bool $loadTimesheetHistory = false): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $with = ['assignedDepartment', 'workLocation', 'assignedShift'];
        if ($loadTimeClockEntries) {
            $with['timeClockEntries'] = static function ($query) use ($loadTimesheetHistory): void {
                $query->with(['workLocation', 'department', 'shift'])
                    ->orderByDesc('clocked_at')
                    ->orderByDesc('id');

                if ($loadTimesheetHistory) {
                    $since = DisplayTimezone::now()->subWeeks(12)->startOfWeek(\Carbon\Carbon::MONDAY)->utc();
                    $query->where('clocked_at', '>=', $since);
                } else {
                    $query->limit(100);
                }
            };
        }

        $employees = Employee::on($conn)
            ->with($with)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name')
            ->get();

        $sinceWeek = DisplayTimezone::now()->subWeeks(12)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $timesheetApprovals = TimesheetApproval::on($conn)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('week_start', '>=', $sinceWeek)
            ->get();

        return [
            'company' => $company,
            'employees' => $employees,
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'timesheetApprovals' => $timesheetApprovals,
        ];
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

    /**
     * Update registration profile fields for an approved (active) employee only.
     */
    public function updateProfile(Request $request, string $companySlug, string $publicId): RedirectResponse
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

        if (($employee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'profile' => 'Employee details can only be edited after the application is approved and active.',
            ]);
        }

        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'full_legal_name' => ['required', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:48'],
            'date_of_birth' => ['nullable', 'string', 'max:32'],
            'sex' => ['nullable', 'string', 'max:32'],
            'marital_status' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:5000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:160'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:48'],
            'emergency_contact_relationship' => ['nullable', 'string', 'max:120'],
            'visa_status' => ['nullable', 'string', 'max:120'],
            'unrestricted_work_rights' => ['nullable', 'string', 'max:32'],
            'visa_expiry' => ['nullable', 'string', 'max:32'],
            'hours_per_week' => ['nullable', 'string', 'max:16'],
            'weekly_availability_summary' => ['nullable', 'string', 'max:5000'],
            'id_documents_summary' => ['nullable', 'string', 'max:5000'],
            'police_check_expiry' => ['nullable', 'string', 'max:32'],
            'police_check_uploaded' => ['nullable', 'string', 'max:32'],
            'fit_to_work_expiry' => ['nullable', 'string', 'max:32'],
            'fit_to_work_uploaded' => ['nullable', 'string', 'max:32'],
            'licences_summary' => ['nullable', 'string', 'max:5000'],
            'insurances_summary' => ['nullable', 'string', 'max:5000'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'bank_account_number' => ['nullable', 'string', 'max:500'],
            'bank_branch_code' => ['nullable', 'string', 'max:32'],
            'bank_name' => ['nullable', 'string', 'max:160'],
            'mode_of_transport' => ['nullable', 'string', 'max:64'],
            'vehicle_registration' => ['nullable', 'string', 'max:64'],
            'vehicle_expiry' => ['nullable', 'string', 'max:32'],
            'vehicle_insurance_uploaded' => ['nullable', 'string', 'max:32'],
            'employee_code' => ['nullable', 'string', 'max:64'],
            'job_title_id' => ['nullable', 'integer'],
            'department_id' => ['nullable', 'integer'],
            'profile_photo' => ['nullable', 'file', 'max:15360'],
            'police_check' => ['nullable', 'file', 'max:15360'],
            'fit_to_work' => ['nullable', 'file', 'max:15360'],
            'vehicle_insurance' => ['nullable', 'file', 'max:15360'],
            'id_document_upload' => ['nullable', 'array'],
            'id_document_upload.*' => ['file', 'max:15360'],
            'licence_upload' => ['nullable', 'array'],
            'licence_upload.*' => ['file', 'max:15360'],
            'insurance_upload' => ['nullable', 'array'],
            'insurance_upload.*' => ['file', 'max:15360'],
            'id_document_type' => ['nullable', 'array'],
            'id_document_type.*' => ['nullable', 'string', 'max:255'],
            'licence_type_row' => ['nullable', 'array'],
            'licence_type_row.*' => ['nullable', 'string', 'max:255'],
            'insurance_type_row' => ['nullable', 'array'],
            'insurance_type_row.*' => ['nullable', 'string', 'max:255'],
        ]);

        $email = (string) $data['email'];
        $emailTaken = Employee::on($conn)
            ->where('email', $email)
            ->where('id', '!=', $employee->id)
            ->exists();
        if ($emailTaken) {
            throw ValidationException::withMessages([
                'email' => 'That email is already in use for another employee.',
            ]);
        }

        $this->assertPicklistOptional($data['marital_status'] ?? null, 'marital_status', 'marital_status');
        $this->assertPicklistOptional($data['visa_status'] ?? null, 'visa_status', 'visa_status');
        $this->assertPicklistOptional($data['unrestricted_work_rights'] ?? null, 'unrestricted_work_rights', 'unrestricted_work_rights');
        $this->assertPicklistOptional($data['mode_of_transport'] ?? null, 'transport_mode', 'mode_of_transport');
        $this->assertPicklistOptional($data['police_check_uploaded'] ?? null, 'unrestricted_work_rights', 'police_check_uploaded');
        $this->assertPicklistOptional($data['fit_to_work_uploaded'] ?? null, 'unrestricted_work_rights', 'fit_to_work_uploaded');
        $this->assertPicklistOptional($data['vehicle_insurance_uploaded'] ?? null, 'unrestricted_work_rights', 'vehicle_insurance_uploaded');

        foreach ($request->input('id_document_type', []) as $val) {
            if (is_string($val) && $val !== '') {
                $this->assertPicklistOptional($val, 'id_document_type', 'id_document_type');
            }
        }
        foreach ($request->input('licence_type_row', []) as $val) {
            if (is_string($val) && $val !== '') {
                $this->assertPicklistOptional($val, 'licence_type', 'licence_type_row');
            }
        }
        foreach ($request->input('insurance_type_row', []) as $val) {
            if (is_string($val) && $val !== '') {
                $this->assertPicklistOptional($val, 'insurance_type', 'insurance_type_row');
            }
        }

        [$firstName, $lastName] = FoundUProfileMapper::splitFullLegalName($data['full_legal_name']);

        $weeklyJson = AdminWeeklyAvailability::encodeFromRequest($request);
        $weeklySummary = AdminWeeklyAvailability::summaryTextFromMobileGrid(
            AdminWeeklyAvailability::mobileGridState($weeklyJson)
        );

        $sexNormalized = $this->normalizeSex($data['sex'] ?? null);

        $nullableInt = static function (mixed $v): ?int {
            if ($v === null || $v === '') {
                return null;
            }
            if (is_numeric($v)) {
                return (int) $v;
            }

            return null;
        };

        $departmentId = $nullableInt($request->input('department_id'));
        $this->assertBelongsToTenant($conn, 'departments', $departmentId);
        $departmentName = $departmentId === null
            ? null
            : Department::on($conn)->whereKey($departmentId)->value('name');

        $jobTitleId = $nullableInt($request->input('job_title_id'));
        $this->assertBelongsToTenant($conn, 'job_titles', $jobTitleId);
        $jobTitleName = $jobTitleId === null
            ? null
            : JobTitle::on($conn)->whereKey($jobTitleId)->value('name');

        $fill = collect($data)
            ->only([
                'email', 'full_legal_name', 'phone',
                'date_of_birth', 'marital_status', 'address',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relationship',
                'visa_status', 'unrestricted_work_rights', 'visa_expiry', 'hours_per_week',
                'weekly_availability_summary', 'id_documents_summary',
                'police_check_expiry', 'police_check_uploaded', 'fit_to_work_expiry', 'fit_to_work_uploaded',
                'licences_summary', 'insurances_summary',
                'bank_account_name', 'bank_account_number', 'bank_branch_code', 'bank_name',
                'mode_of_transport',
                'employee_code',
            ])
            ->merge([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'sex' => $sexNormalized,
                'weekly_availability_json' => $weeklyJson,
                'weekly_availability_summary' => $weeklySummary,
                'department_id' => $departmentId,
                'department' => $departmentName,
                'job_title_id' => $jobTitleId,
                'job_title' => $jobTitleName,
            ])
            ->all();

        if ($this->isUnrestrictedWorkRightsYes($fill['unrestricted_work_rights'] ?? null)) {
            $fill['visa_expiry'] = null;
        }

        if (! $this->transportIsOwnVehicle($fill['mode_of_transport'] ?? null)) {
            $fill['vehicle_registration'] = null;
            $fill['vehicle_expiry'] = null;
            $fill['vehicle_insurance_uploaded'] = null;
            $fill['vehicle_insurance_path'] = null;
        } else {
            $fill['vehicle_registration'] = $data['vehicle_registration'] ?? null;
            $fill['vehicle_expiry'] = $data['vehicle_expiry'] ?? null;
            $fill['vehicle_insurance_uploaded'] = $data['vehicle_insurance_uploaded'] ?? null;
        }

        $bankNum = trim((string) ($fill['bank_account_number'] ?? ''));
        if ($bankNum === '' || preg_match('/^X{10}\d{4}$/i', $bankNum)) {
            unset($fill['bank_account_number']);
        }

        foreach (['date_of_birth', 'visa_expiry', 'police_check_expiry', 'fit_to_work_expiry', 'vehicle_expiry'] as $dateField) {
            if (array_key_exists($dateField, $fill)) {
                $fill[$dateField] = RegistrationDisplay::persistAdminDateField(
                    $fill[$dateField],
                    $request->input($dateField.'_storage_format')
                );
            }
        }

        $employee->forceFill($fill)->save();

        $this->applyJsonRowPicklistFields($request, $employee);
        $this->applyJsonRowExpiryFields($request, $employee);
        $employee->save();

        app(RegistrationDocumentStorage::class)->attach($request, $employee, $sessionCompany->slug);

        $uploadFlags = [];
        if ($request->hasFile('police_check')) {
            $uploadFlags['police_check_uploaded'] = 'Yes';
        }
        if ($request->hasFile('fit_to_work')) {
            $uploadFlags['fit_to_work_uploaded'] = 'Yes';
        }
        if ($uploadFlags !== []) {
            $employee->forceFill($uploadFlags)->save();
        }

        if (! $this->transportIsOwnVehicle($employee->mode_of_transport)) {
            $employee->forceFill([
                'vehicle_registration' => null,
                'vehicle_expiry' => null,
                'vehicle_insurance_uploaded' => null,
                'vehicle_insurance_path' => null,
            ])->save();
        }

        return redirect()
            ->route('admin.registrations.show', ['companySlug' => $companySlug, 'publicId' => $publicId])
            ->with('status', 'Employee profile updated.');
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
            ->route('admin.employees.assignments')
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

    /**
     * @param  non-empty-string  $errorField  Request attribute name for validation errors
     */
    private function applyJsonRowPicklistFields(Request $request, Employee $employee): void
    {
        $this->patchJsonRowsWithPicklist(
            $employee,
            'id_documents_json',
            'documentKey',
            'documentType',
            $request->input('id_document_type', []),
            'id_document_type',
        );
        $this->patchJsonRowsWithPicklist(
            $employee,
            'licences_json',
            'id',
            'documentType',
            $request->input('licence_type_row', []),
            'licence_type',
        );
        $this->patchJsonRowsWithPicklist(
            $employee,
            'insurances_json',
            'id',
            'documentType',
            $request->input('insurance_type_row', []),
            'insurance_type',
        );
    }

    private function applyJsonRowExpiryFields(Request $request, Employee $employee): void
    {
        $this->patchJsonRowsWithExpiry(
            $employee,
            'licences_json',
            'id',
            $request->input('licence_expiry_row', []),
        );
        $this->patchJsonRowsWithExpiry(
            $employee,
            'insurances_json',
            'id',
            $request->input('insurance_expiry_row', []),
        );

        /** @var array<int, array<string, mixed>>|null $licences */
        $licences = $employee->licences_json;
        if (is_array($licences)) {
            $employee->licences_json = RegistrationDisplay::normalizeDocumentJsonExpiryRows($licences);
            $employee->licences_summary = RegistrationDisplay::rebuildDocumentRowsSummary($employee->licences_json);
        }

        /** @var array<int, array<string, mixed>>|null $insurances */
        $insurances = $employee->insurances_json;
        if (is_array($insurances)) {
            $employee->insurances_json = RegistrationDisplay::normalizeDocumentJsonExpiryRows($insurances);
            $employee->insurances_summary = RegistrationDisplay::rebuildDocumentRowsSummary($employee->insurances_json);
        }
    }

    /**
     * @param  array<string, mixed>  $submitted  row id => HTML date input (Y-m-d)
     */
    private function patchJsonRowsWithExpiry(
        Employee $employee,
        string $jsonAttribute,
        string $rowIdKey,
        mixed $submitted,
    ): void {
        if (! is_array($submitted) || $submitted === []) {
            return;
        }

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $employee->{$jsonAttribute};
        if (! is_array($rows) || $rows === []) {
            return;
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row[$rowIdKey]) && is_scalar($row[$rowIdKey]) ? (string) $row[$rowIdKey] : '';
            if ($id === '' || ! array_key_exists($id, $submitted)) {
                continue;
            }
            $iso = RegistrationDisplay::toNullableIsoDate($submitted[$id]);
            if ($iso === null) {
                unset($rows[$i]['expiry'], $rows[$i]['expiry_date']);
                continue;
            }
            $rows[$i]['expiry'] = $iso;
            $rows[$i]['expiry_date'] = $iso;
        }

        $employee->{$jsonAttribute} = $rows;
    }

    /**
     * @param  array<string, mixed>  $submitted  key => picklist value
     */
    private function patchJsonRowsWithPicklist(
        Employee $employee,
        string $jsonAttribute,
        string $rowIdKey,
        string $typeKey,
        mixed $submitted,
        string $picklistKey,
    ): void {
        if (! is_array($submitted) || $submitted === []) {
            return;
        }

        /** @var array<int, array<string, mixed>>|null $rows */
        $rows = $employee->{$jsonAttribute};
        if (! is_array($rows) || $rows === []) {
            return;
        }

        foreach ($rows as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row[$rowIdKey]) && is_scalar($row[$rowIdKey]) ? (string) $row[$rowIdKey] : '';
            if ($id === '' || ! array_key_exists($id, $submitted)) {
                continue;
            }
            $val = $submitted[$id];
            if (! is_string($val) || trim($val) === '') {
                continue;
            }
            $rows[$i][$typeKey] = $val;
        }

        $employee->{$jsonAttribute} = $rows;
    }

    private function normalizeSex(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $s = strtolower(trim($value));
        if (in_array($s, ['male', 'm', 'man'], true) || str_starts_with($s, 'male')) {
            return 'Male';
        }
        if (in_array($s, ['female', 'f', 'woman'], true) || str_starts_with($s, 'female')) {
            return 'Female';
        }
        if (strcasecmp(trim($value), 'Male') === 0) {
            return 'Male';
        }
        if (strcasecmp(trim($value), 'Female') === 0) {
            return 'Female';
        }

        return null;
    }

    private function transportIsOwnVehicle(?string $mode): bool
    {
        if ($mode === null || trim($mode) === '') {
            return false;
        }

        return strcasecmp(trim($mode), 'Own vehicle') === 0;
    }

    private function isUnrestrictedWorkRightsYes(?string $value): bool
    {
        if ($value === null || trim($value) === '') {
            return false;
        }

        return strcasecmp(trim($value), 'Yes') === 0;
    }

    private function assertPicklistOptional(?string $value, string $picklistKey, string $errorField): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = RegistrationPicklistItem::query()
            ->where('picklist_key', $picklistKey)
            ->where('value', $value)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $errorField => 'The selected value is not valid for this list.',
            ]);
        }
    }
}
