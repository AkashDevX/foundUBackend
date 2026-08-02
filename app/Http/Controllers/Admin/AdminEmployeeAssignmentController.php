<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAssignmentShift;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\JobTitle;
use App\Models\LeaveType;
use App\Models\OrganizationPortalUser;
use App\Models\RegistrationPicklistItem;
use App\Models\Shift;
use App\Models\TimesheetApproval;
use App\Models\TimeClockEntry;
use App\Models\WorkLocation;
use App\Services\RegistrationDocumentStorage;
use App\Support\AdminEmployeeProfileView;
use App\Support\AdminTimeClockTimesheet;
use App\Support\AdminTimesheetApproval;
use App\Support\AdminWeeklyAvailability;
use App\Support\AdminWeeklySchedule;
use App\Support\DisplayTimezone;
use App\Support\FoundUProfileMapper;
use App\Support\PayrollEmployeeRates;
use App\Support\RegistrationDisplay;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminEmployeeAssignmentController extends Controller
{
    public function assignments(Request $request): View
    {
        $data = $this->employeePageData($request, loadTimeClockEntries: false);

        $selectedProfilePublicId = $request->query('profile', '');
        if (! is_string($selectedProfilePublicId)) {
            $selectedProfilePublicId = '';
        }

        $selectedProfileEmployee = null;
        $profileData = [];
        if ($selectedProfilePublicId !== '') {
            $selectedProfileEmployee = $data['employees']->firstWhere('public_id', $selectedProfilePublicId);
            if ($selectedProfileEmployee !== null) {
                $profileData = AdminEmployeeProfileView::viewData($request, $data['company'], $selectedProfileEmployee);
            }
        }

        return view('admin.employees-assignments', array_merge($data, $profileData, [
            'employees' => $data['employees'],
            'selectedProfileEmployee' => $selectedProfileEmployee,
            'selectedProfilePublicId' => $selectedProfilePublicId,
        ]));
    }

    public function profiles(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $employees = Employee::on($conn)
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->get();

        $selectedPublicId = $request->query('employee', '');
        if (! is_string($selectedPublicId)) {
            $selectedPublicId = '';
        }

        $selectedEmployee = null;
        $profileData = [];

        if ($selectedPublicId !== '') {
            $selectedEmployee = $employees->firstWhere('public_id', $selectedPublicId);
            if ($selectedEmployee !== null) {
                $profileData = AdminEmployeeProfileView::viewData($request, $company, $selectedEmployee);
            }
        }

        return view('admin.employees-profiles', array_merge($profileData, [
            'company' => $company,
            'employees' => $employees,
            'selectedEmployee' => $selectedEmployee,
            'selectedPublicId' => $selectedPublicId,
        ]));
    }

    public function timeClock(Request $request): View
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $timesheetStatusFilter = $request->query('timesheet_status', 'all');
        if (! in_array($timesheetStatusFilter, ['all', 'pending', 'approved', 'rejected'], true)) {
            $timesheetStatusFilter = 'all';
        }

        $departmentId = $request->query('department_id');
        $workLocationId = $request->query('work_location_id');
        $employeePublicId = $request->query('employee');
        $selectedWeekParam = $request->query('week');
        $selectedWeek = is_string($selectedWeekParam) && $selectedWeekParam !== ''
            ? AdminWeeklySchedule::resolveWeekStart($selectedWeekParam)
            : null;

        $indexWeekStart = AdminWeeklySchedule::resolveWeekStart(null)->subWeeks(11);
        $indexWeekEnd = AdminWeeklySchedule::resolveWeekStart(null)->copy()->addDays(6);

        $employeesQuery = Employee::on($conn)
            ->with([
                'assignedDepartment',
                'assignedJobTitle',
                'workLocation',
                'assignedShift',
                'timeClockEntries' => static function ($query) use ($indexWeekStart, $indexWeekEnd): void {
                    $query->with(['workLocation', 'department', 'shift'])
                        ->whereBetween('clocked_at', [
                            $indexWeekStart->copy()->startOfDay()->utc(),
                            $indexWeekEnd->copy()->endOfDay()->utc(),
                        ])
                        ->orderBy('clocked_at')
                        ->orderBy('id');
                },
            ])
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name');

        if (is_string($departmentId) && $departmentId !== '') {
            $employeesQuery->where('department_id', (int) $departmentId);
        }

        if (is_string($workLocationId) && $workLocationId !== '') {
            $employeesQuery->where('work_location_id', (int) $workLocationId);
        }

        if (is_string($employeePublicId) && $employeePublicId !== '') {
            $employeesQuery->where('public_id', $employeePublicId);
        }

        $employees = $employeesQuery->get();

        $scheduleShifts = EmployeeScheduleShift::on($conn)
            ->with(['shiftTemplate', 'jobTitle', 'department', 'workLocation'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$indexWeekStart->toDateString(), $indexWeekEnd->toDateString()])
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $timesheetApprovals = TimesheetApproval::on($conn)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('work_date', [$indexWeekStart->toDateString(), $indexWeekEnd->toDateString()])
            ->get();

        $weekIndex = AdminTimeClockTimesheet::buildWeekIndex(
            $employees,
            $scheduleShifts,
            $timesheetApprovals,
        );

        $timesheetGroups = [];
        $selectedWeekLabel = null;

        if ($selectedWeek !== null) {
            $selectedWeekLabel = AdminTimeClockTimesheet::formatCompactWeekLabel($selectedWeek);
            $timesheet = AdminTimeClockTimesheet::buildGroups(
                $employees,
                $selectedWeek,
                $scheduleShifts,
                $timesheetApprovals,
                $timesheetStatusFilter === 'all' ? null : $timesheetStatusFilter
            );
            $timesheetGroups = $timesheet['groups'];
        }

        $filterParams = array_filter([
            'department_id' => is_string($departmentId) && $departmentId !== '' ? $departmentId : null,
            'work_location_id' => is_string($workLocationId) && $workLocationId !== '' ? $workLocationId : null,
            'employee' => is_string($employeePublicId) && $employeePublicId !== '' ? $employeePublicId : null,
            'timesheet_status' => $timesheetStatusFilter !== 'all' ? $timesheetStatusFilter : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $weekDetailsUrl = static function (string $weekStart) use ($filterParams): string {
            return route('admin.employees.time-clock', array_filter([
                ...$filterParams,
                'week' => $weekStart,
            ]));
        };

        $redirectQuery = array_filter([
            'week' => $selectedWeek?->toDateString(),
            ...$filterParams,
        ]);

        return view('admin.employees-time-clock', [
            'company' => $company,
            'weekIndex' => $weekIndex,
            'selectedWeek' => $selectedWeek,
            'selectedWeekLabel' => $selectedWeekLabel,
            'timesheetGroups' => $timesheetGroups,
            'timesheetStatusFilter' => $timesheetStatusFilter,
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'employees' => Employee::on($conn)
                ->where('employment_status', 'active')
                ->orderBy('full_legal_name')
                ->get(['id', 'public_id', 'full_legal_name', 'email']),
            'filters' => [
                'department_id' => is_string($departmentId) ? $departmentId : '',
                'work_location_id' => is_string($workLocationId) ? $workLocationId : '',
                'employee' => is_string($employeePublicId) ? $employeePublicId : '',
            ],
            'filterParams' => $filterParams,
            'weekDetailsUrl' => $weekDetailsUrl,
            'redirectQuery' => $redirectQuery,
            'listUrl' => route('admin.employees.time-clock'),
            'clearFiltersUrl' => route('admin.employees.time-clock', array_filter([
                'week' => $selectedWeek?->toDateString(),
            ])),
        ]);
    }

    public function approveTimesheet(Request $request): RedirectResponse
    {
        return $this->reviewTimesheet($request, TimesheetApproval::STATUS_APPROVED, 'Timesheet approved.');
    }

    public function rejectTimesheet(Request $request): RedirectResponse
    {
        return $this->reviewTimesheet($request, TimesheetApproval::STATUS_REJECTED, 'Timesheet rejected.');
    }

    public function resetTimesheet(Request $request): RedirectResponse
    {
        return $this->reviewTimesheet($request, TimesheetApproval::STATUS_PENDING, 'Timesheet marked as pending.');
    }

    public function updateTimesheetPunches(Request $request): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'employee' => ['required', 'string', 'max:64'],
            'work_date' => ['required', 'date'],
            'clock_in_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'clock_out_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'clock_in_entry_id' => ['nullable', 'integer'],
            'clock_out_entry_id' => ['nullable', 'integer'],
        ]);

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $data['employee'])
            ->where('employment_status', 'active')
            ->firstOrFail();

        $workDate = AdminTimesheetApproval::normalizeWorkDate($data['work_date']);
        $clockInEntryId = ! empty($data['clock_in_entry_id']) ? (int) $data['clock_in_entry_id'] : null;
        if ($clockInEntryId !== null) {
            $this->assertTimesheetSessionEditable($conn, (int) $employee->id, $clockInEntryId);
        }
        $this->applyTimesheetPunchTimes($conn, $employee, $data);

        return redirect()
            ->route('admin.employees.time-clock', array_filter([
                'employee' => $request->input('list_employee'),
                'week' => $request->input('week'),
                'department_id' => $request->input('department_id'),
                'work_location_id' => $request->input('work_location_id'),
                'timesheet_status' => $request->input('timesheet_status'),
            ]))
            ->with('status', 'Clock times updated.');
    }

    private function reviewTimesheet(Request $request, string $status, string $flashMessage): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'employee' => ['required', 'string', 'max:64'],
            'work_date' => ['required', 'date'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
            'clock_in_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'clock_out_at' => ['nullable', 'date_format:Y-m-d\TH:i'],
            'clock_in_entry_id' => ['nullable', 'integer'],
            'clock_out_entry_id' => ['nullable', 'integer'],
        ]);

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $data['employee'])
            ->where('employment_status', 'active')
            ->firstOrFail();

        $workDate = AdminTimesheetApproval::normalizeWorkDate($data['work_date']);
        $tz = DisplayTimezone::name();
        $dayStart = Carbon::parse($workDate, $tz)->startOfDay()->utc();
        $dayEnd = Carbon::parse($workDate, $tz)->endOfDay()->utc();

        $entries = TimeClockEntry::on($conn)
            ->where('employee_id', $employee->id)
            ->whereBetween('clocked_at', [$dayStart, $dayEnd])
            ->orderBy('clocked_at')
            ->get();

        $clockInEntryIds = self::resolveTimesheetClockInEntryIds($data, $entries);

        if ($clockInEntryIds === []) {
            $hasSchedule = EmployeeScheduleShift::on($conn)
                ->where('employee_id', $employee->id)
                ->where('scheduled_date', $workDate)
                ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
                ->exists();

            if ($entries->isEmpty() && ! $hasSchedule) {
                throw ValidationException::withMessages([
                    'work_date' => 'No scheduled shift or clock activity found for that day.',
                ]);
            }

            throw ValidationException::withMessages([
                'clock_in_entry_id' => 'Select a clock-in session to review.',
            ]);
        }

        if ($status !== TimesheetApproval::STATUS_PENDING) {
            foreach ($clockInEntryIds as $clockInEntryId) {
                $this->assertTimesheetSessionEditable($conn, (int) $employee->id, $clockInEntryId);
            }
            $this->applyTimesheetPunchTimes($conn, $employee, $data);
        }

        $summary = \App\Support\AdminTimeClockDisplay::summarizeWorkSessions($entries);

        foreach ($clockInEntryIds as $clockInEntryId) {
            $session = AdminTimesheetApproval::sessionSummaryForClockIn($summary, $clockInEntryId);
            $sessionSeconds = is_array($session) ? (int) ($session['seconds'] ?? 0) : 0;
            $completedSessions = is_array($session) && ! empty($session['clock_out_id']) ? 1 : 0;

            $attributes = [
                'work_date' => $workDate,
                'total_seconds' => $sessionSeconds,
                'completed_sessions' => $completedSessions,
                'status' => $status,
            ];

            if ($status === TimesheetApproval::STATUS_PENDING) {
                $attributes['reviewed_by'] = null;
                $attributes['reviewed_at'] = null;
                $attributes['review_notes'] = null;
            } else {
                $attributes['reviewed_by'] = $portalUser->name ?: $portalUser->email;
                $attributes['reviewed_at'] = now('UTC');
                $attributes['review_notes'] = $data['review_notes'] ?? null;
            }

            TimesheetApproval::on($conn)->updateOrCreate(
                [
                    'employee_id' => $employee->id,
                    'clock_in_entry_id' => $clockInEntryId,
                ],
                $attributes
            );
        }

        return redirect()
            ->route('admin.employees.time-clock', array_filter([
                'employee' => $request->input('list_employee'),
                'week' => $request->input('week'),
                'department_id' => $request->input('department_id'),
                'work_location_id' => $request->input('work_location_id'),
                'timesheet_status' => $request->input('timesheet_status'),
            ]))
            ->with('status', $flashMessage);
    }

    private function assertTimesheetSessionEditable(string $conn, int $employeeId, int $clockInEntryId): void
    {
        $approval = TimesheetApproval::on($conn)
            ->where('employee_id', $employeeId)
            ->where('clock_in_entry_id', $clockInEntryId)
            ->first();

        $status = $approval?->status ?? TimesheetApproval::STATUS_PENDING;

        if (! in_array($status, [TimesheetApproval::STATUS_PENDING, TimesheetApproval::STATUS_REJECTED], true)) {
            throw ValidationException::withMessages([
                'clock_in_entry_id' => 'This shift is already approved and cannot be edited.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  \Illuminate\Support\Collection<int, TimeClockEntry>  $dayEntries
     * @return list<int>
     */
    private static function resolveTimesheetClockInEntryIds(array $data, Collection $dayEntries): array
    {
        if (! empty($data['clock_in_entry_id'])) {
            return [(int) $data['clock_in_entry_id']];
        }

        $summary = \App\Support\AdminTimeClockDisplay::summarizeWorkSessions($dayEntries);
        $ids = [];

        foreach ($summary['hours_by_entry_id'] as $session) {
            $clockInId = (int) ($session['clock_in_id'] ?? 0);
            if ($clockInId > 0) {
                $ids[] = $clockInId;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function applyTimesheetPunchTimes(string $conn, Employee $employee, array $data): void
    {
        $tz = DisplayTimezone::name();
        $clockInAt = null;
        $clockOutAt = null;

        if (! empty($data['clock_in_entry_id']) && ! empty($data['clock_in_at'])) {
            /** @var TimeClockEntry $clockIn */
            $clockIn = TimeClockEntry::on($conn)
                ->where('employee_id', $employee->id)
                ->whereKey((int) $data['clock_in_entry_id'])
                ->firstOrFail();

            if ($clockIn->event_type !== TimeClockEntry::EVENT_CLOCK_IN) {
                throw ValidationException::withMessages([
                    'clock_in_at' => 'The selected clock-in record is invalid.',
                ]);
            }

            $clockInAt = Carbon::parse((string) $data['clock_in_at'], $tz)->utc();
            $clockIn->forceFill(['clocked_at' => $clockInAt])->save();
        }

        if (! empty($data['clock_out_entry_id']) && ! empty($data['clock_out_at'])) {
            /** @var TimeClockEntry $clockOut */
            $clockOut = TimeClockEntry::on($conn)
                ->where('employee_id', $employee->id)
                ->whereKey((int) $data['clock_out_entry_id'])
                ->firstOrFail();

            if ($clockOut->event_type !== TimeClockEntry::EVENT_CLOCK_OUT) {
                throw ValidationException::withMessages([
                    'clock_out_at' => 'The selected clock-out record is invalid.',
                ]);
            }

            $clockOutAt = Carbon::parse((string) $data['clock_out_at'], $tz)->utc();
            $clockOut->forceFill(['clocked_at' => $clockOutAt])->save();
        }

        if ($clockInAt !== null && $clockOutAt !== null && $clockOutAt->lessThanOrEqualTo($clockInAt)) {
            throw ValidationException::withMessages([
                'clock_out_at' => 'Clock out must be after clock in.',
            ]);
        }
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

        $with = ['assignedDepartment', 'workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate'];
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

        $sinceDate = DisplayTimezone::now()->subWeeks(12)->startOfWeek(\Carbon\Carbon::MONDAY)->toDateString();
        $timesheetApprovals = TimesheetApproval::on($conn)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->where('work_date', '>=', $sinceDate)
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
            ->back()
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
            'employment_type' => ['nullable', 'string', 'in:full_time,part_time,casual'],
            'award_level' => ['nullable', 'string', 'in:level_1,level_2'],
            'is_non_rotating_shift' => ['nullable', 'boolean'],
            'payroll_rates' => ['nullable', 'array'],
            'payroll_rates.*' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'allowance_name' => ['nullable', 'array'],
            'allowance_name.*' => ['nullable', 'string', 'max:120'],
            'allowance_amount' => ['nullable', 'array'],
            'allowance_amount.*' => ['nullable', 'numeric', 'min:0', 'max:99999'],
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
            'remove_profile_photo' => ['nullable', 'boolean'],
            'remove_police_check' => ['nullable', 'boolean'],
            'remove_fit_to_work' => ['nullable', 'boolean'],
            'remove_vehicle_insurance' => ['nullable', 'boolean'],
            'remove_id_document_upload' => ['nullable', 'array'],
            'remove_id_document_upload.*' => ['nullable', 'boolean'],
            'remove_licence_upload' => ['nullable', 'array'],
            'remove_licence_upload.*' => ['nullable', 'boolean'],
            'remove_insurance_upload' => ['nullable', 'array'],
            'remove_insurance_upload.*' => ['nullable', 'boolean'],
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
                'employment_type', 'award_level', 'is_non_rotating_shift',
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

        if ($request->has('allowance_name')) {
            $allowanceNames = $request->input('allowance_name', []);
            $allowanceAmounts = $request->input('allowance_amount', []);
            $allowances = [];
            foreach ($allowanceNames as $i => $name) {
                $name = trim((string) $name);
                $amount = (float) ($allowanceAmounts[$i] ?? 0);
                if ($name !== '' && $amount > 0) {
                    $allowances[] = ['name' => $name, 'amount' => round($amount, 2)];
                }
            }
            $fill['payroll_allowances_json'] = $allowances === [] ? null : $allowances;
        }

        $employee->forceFill([
            'employment_type' => $fill['employment_type'] ?? null,
            'award_level' => $fill['award_level'] ?? null,
        ]);
        $mergedRates = PayrollEmployeeRates::fromRequest(
            (array) ($data['payroll_rates'] ?? []),
            $conn,
            $employee
        );
        $fill['payroll_rates_json'] = PayrollEmployeeRates::toStoredOverrides($mergedRates, $conn, $employee);
        if ($request->has('is_non_rotating_shift')) {
            $fill['is_non_rotating_shift'] = $request->boolean('is_non_rotating_shift');
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
        } elseif ($request->boolean('remove_police_check')) {
            $uploadFlags['police_check_uploaded'] = 'No';
        }
        if ($request->hasFile('fit_to_work')) {
            $uploadFlags['fit_to_work_uploaded'] = 'Yes';
        } elseif ($request->boolean('remove_fit_to_work')) {
            $uploadFlags['fit_to_work_uploaded'] = 'No';
        }
        if ($request->boolean('remove_vehicle_insurance')) {
            $employee->forceFill(['vehicle_insurance_uploaded' => null])->save();
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
            ->back()
            ->with('status', 'Employee profile updated.');
    }

    public function storeLeave(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();
        abort_unless($sessionCompany->slug === $companySlug, 403);

        $conn = $sessionCompany->tenant_connection;

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->where('employment_status', 'active')
            ->firstOrFail();

        $activeLeaveTypes = LeaveType::on($conn)->where('is_active', true)->get()->keyBy('code');

        // Restrict recordable leave to the types this employee is entitled to.
        // Fall back to all active types when no entitlements have been assigned yet.
        $entitledTypeIds = EmployeeLeaveEntitlement::on($conn)
            ->where('employee_id', $employee->id)
            ->pluck('leave_type_id')
            ->all();
        $allowedTypes = $entitledTypeIds === []
            ? $activeLeaveTypes
            : $activeLeaveTypes->whereIn('id', $entitledTypeIds);

        $allowedCodes = $allowedTypes->keys()->all();
        if ($allowedCodes === []) {
            $allowedCodes = [EmployeeLeaveRecord::TYPE_SICK, EmployeeLeaveRecord::TYPE_ANNUAL];
        }

        $data = $request->validate([
            'leave_type' => ['required', 'string', Rule::in($allowedCodes)],
            'leave_date' => ['required', 'date'],
            'leave_hours' => ['required', 'numeric', 'min:0.25', 'max:24'],
            'leave_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $leaveType = $activeLeaveTypes->get($data['leave_type']);
        $isPaid = $leaveType === null ? true : (bool) $leaveType->is_paid;

        $rates = PayrollEmployeeRates::forEmployee($conn, $employee);
        $ordinary = PayrollEmployeeRates::ordinaryHourlyRate($rates);

        EmployeeLeaveRecord::on($conn)->create([
            'employee_id' => $employee->id,
            'leave_type' => $data['leave_type'],
            'is_paid' => $isPaid,
            'leave_date' => $data['leave_date'],
            'hours' => round((float) $data['leave_hours'], 2),
            'hourly_rate' => $isPaid && $ordinary > 0 ? $ordinary : null,
            'status' => EmployeeLeaveRecord::STATUS_PENDING,
            'notes' => $data['leave_notes'] ?? null,
            'created_by' => $portalUser->name ?: $portalUser->email,
        ]);

        return redirect()
            ->back()
            ->with('status', $isPaid
                ? 'Leave recorded — will be paid in the next finalized pay run for that fortnight.'
                : 'Unpaid leave recorded — will be tracked in the next finalized pay run for that fortnight.');
    }

    /**
     * Resolve the active employee for a per-employee leave action.
     */
    private function activeEmployeeForLeave(Request $request, string $companySlug, string $publicId): array
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $sessionCompany = $portalUser->company()->firstOrFail();
        abort_unless($sessionCompany->slug === $companySlug, 403);

        $conn = $sessionCompany->tenant_connection;

        /** @var Employee $employee */
        $employee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->where('employment_status', 'active')
            ->firstOrFail();

        return [$portalUser, $conn, $employee];
    }

    public function storeLeaveEntitlement(Request $request, string $companySlug, string $publicId): RedirectResponse
    {
        [$portalUser, $conn, $employee] = $this->activeEmployeeForLeave($request, $companySlug, $publicId);

        $data = $request->validate([
            'leave_type_ids' => ['required', 'array', 'min:1'],
            'leave_type_ids.*' => [
                'integer',
                Rule::exists($conn.'.leave_types', 'id')->where('is_active', true),
            ],
        ], [], [
            'leave_type_ids' => 'leave types',
        ]);

        $alreadyAssigned = EmployeeLeaveEntitlement::on($conn)
            ->where('employee_id', $employee->id)
            ->pluck('leave_type_id')
            ->all();

        $requestedIds = array_unique(array_map('intval', $data['leave_type_ids']));
        $typesById = LeaveType::on($conn)->whereIn('id', $requestedIds)->get()->keyBy('id');

        $assigned = 0;
        foreach ($requestedIds as $typeId) {
            if (in_array($typeId, $alreadyAssigned, true)) {
                continue;
            }

            $type = $typesById->get($typeId);

            EmployeeLeaveEntitlement::on($conn)->create([
                'employee_id' => $employee->id,
                'leave_type_id' => $typeId,
                'entitlement_hours' => $type && $type->default_annual_hours !== null
                    ? (float) $type->default_annual_hours
                    : null,
                'notes' => null,
                'created_by' => $portalUser->name ?: $portalUser->email,
            ]);
            $assigned++;
        }

        if ($assigned === 0) {
            return redirect()->back()
                ->withErrors(['leave_type_ids' => 'Those leave types are already assigned to the employee.']);
        }

        return redirect()->back()->with('status', $assigned === 1
            ? 'Leave type assigned to employee.'
            : $assigned.' leave types assigned to employee.');
    }

    public function updateLeaveEntitlement(Request $request, string $companySlug, string $publicId, int $entitlement): RedirectResponse
    {
        [, $conn, $employee] = $this->activeEmployeeForLeave($request, $companySlug, $publicId);

        $data = $request->validate([
            'entitlement_hours' => ['nullable', 'numeric', 'min:0', 'max:9999.99'],
            'entitlement_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $target = EmployeeLeaveEntitlement::on($conn)
            ->where('employee_id', $employee->id)
            ->whereKey($entitlement)
            ->firstOrFail();

        $target->forceFill([
            'entitlement_hours' => isset($data['entitlement_hours']) ? (float) $data['entitlement_hours'] : null,
            'notes' => $data['entitlement_notes'] ?? null,
        ])->save();

        return redirect()->back()->with('status', 'Leave entitlement updated.');
    }

    public function destroyLeaveEntitlement(Request $request, string $companySlug, string $publicId, int $entitlement): RedirectResponse
    {
        [, $conn, $employee] = $this->activeEmployeeForLeave($request, $companySlug, $publicId);

        $target = EmployeeLeaveEntitlement::on($conn)
            ->where('employee_id', $employee->id)
            ->whereKey($entitlement)
            ->firstOrFail();

        $target->delete();

        return redirect()->back()->with('status', 'Leave entitlement removed.');
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
            'assignment_effective_from' => ['nullable', 'date'],
            'assignment_notes' => ['nullable', 'string', 'max:5000'],
            'assignment_shifts' => ['nullable', 'array'],
            'assignment_shifts.*.shift_id' => ['nullable', 'integer'],
            'assignment_shifts.*.unpaid_break_minutes' => ['nullable', 'integer', 'min:0', 'max:480'],
        ]);

        $this->assertBelongsToTenant($conn, 'departments', $data['department_id'] ?? null);
        $this->assertBelongsToTenant($conn, 'work_locations', $data['work_location_id'] ?? null);

        $assignmentShifts = collect($data['assignment_shifts'] ?? [])
            ->map(static function (array $row) use ($nullableInt): ?array {
                $shiftId = $nullableInt($row['shift_id'] ?? null);
                if ($shiftId === null) {
                    return null;
                }

                $breakMinutes = $row['unpaid_break_minutes'] ?? null;

                return [
                    'shift_id' => $shiftId,
                    'unpaid_break_minutes' => $breakMinutes === null || $breakMinutes === '' ? 0 : (int) $breakMinutes,
                ];
            })
            ->filter()
            ->values();

        foreach ($assignmentShifts as $row) {
            $this->assertBelongsToTenant($conn, 'shifts', $row['shift_id']);
        }

        $primaryShiftId = $assignmentShifts->first()['shift_id'] ?? null;

        $employee->forceFill([
            'department_id' => $data['department_id'] ?? null,
            'work_location_id' => $data['work_location_id'] ?? null,
            'shift_id' => $primaryShiftId,
            'assignment_effective_from' => $data['assignment_effective_from'] ?? null,
            'assignment_notes' => $data['assignment_notes'] ?? null,
        ])->save();

        EmployeeAssignmentShift::on($conn)
            ->where('employee_id', $employee->id)
            ->delete();

        foreach ($assignmentShifts as $index => $row) {
            EmployeeAssignmentShift::on($conn)->create([
                'employee_id' => $employee->id,
                'shift_id' => $row['shift_id'],
                'unpaid_break_minutes' => $row['unpaid_break_minutes'],
                'sort_order' => $index,
            ]);
        }
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
