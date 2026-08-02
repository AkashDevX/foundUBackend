<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveEntitlement;
use App\Models\EmployeeLeaveRecord;
use App\Models\EmployeeScheduleShift;
use App\Models\LeaveType;
use App\Models\OrganizationPortalUser;
use App\Models\Shift;
use App\Models\TimeOffRequest;
use App\Models\WorkLocation;
use App\Support\AdminWeeklySchedule;
use App\Support\PayrollEmployeeRates;
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

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $reviewedBy = $portalUser->name ?: $portalUser->email;

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->applyEmployeeShiftDefaults($data, $employee, $conn);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);
            $this->cancelApprovedTimeOffRequestsForDay(
                $conn,
                (int) $employee->id,
                $data['scheduled_date'],
                $reviewedBy,
            );
        } else {
            $this->clearShiftsForDay($conn, (int) $employee->id, $data['scheduled_date']);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);
        }

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->create($this->scheduleEntryAttributes($data, $employee, $portalUser->name));
        $this->syncTimeOffLeaveRecord($conn, $entry, $data, $employee, $reviewedBy);

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF && ! empty($data['time_off_request_id'])) {
            $this->approveTimeOffRequest(
                $conn,
                (int) $data['time_off_request_id'],
                $employee,
                $entry,
                $reviewedBy,
            );
        }

        $message = $data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF
            ? 'Day off saved to the weekly schedule.'
            : 'Shift saved to the weekly schedule.';

        return $this->redirectBack($request, $message);
    }

    public function rejectTimeOffRequest(Request $request, int $timeOffRequest): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;

        $data = $request->validate([
            'decision_note' => ['nullable', 'string', 'max:500'],
        ]);

        /** @var TimeOffRequest $req */
        $req = TimeOffRequest::on($conn)->findOrFail($timeOffRequest);

        if ($req->status === TimeOffRequest::STATUS_PENDING) {
            $note = isset($data['decision_note']) && trim((string) $data['decision_note']) !== ''
                ? trim((string) $data['decision_note'])
                : null;

            $req->fill([
                'status' => TimeOffRequest::STATUS_REJECTED,
                'decision_note' => $note,
                'reviewed_by' => $portalUser->name ?: $portalUser->email,
                'reviewed_at' => now(),
            ])->save();
        }

        return $this->redirectBack($request, 'Time-off request rejected.');
    }

    /**
     * Link an approved day off back to the originating request so the employee sees the outcome.
     */
    private function approveTimeOffRequest(string $conn, int $requestId, Employee $employee, EmployeeScheduleShift $entry, ?string $reviewedBy): void
    {
        /** @var TimeOffRequest|null $req */
        $req = TimeOffRequest::on($conn)->find($requestId);
        if ($req === null || (int) $req->employee_id !== (int) $employee->id) {
            return;
        }

        $req->fill([
            'status' => TimeOffRequest::STATUS_APPROVED,
            'reviewed_by' => $reviewedBy,
            'reviewed_at' => now(),
            'schedule_shift_id' => $entry->id,
            'leave_record_id' => $entry->leave_record_id,
        ])->save();
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

        /** @var OrganizationPortalUser|null $portalUser */
        $portalUser = $request->user('portal');
        $createdBy = $portalUser?->name ?: $portalUser?->email;

        $wasTimeOff = $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF;

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->applyEmployeeShiftDefaults($data, $employee, $conn);
            // Keep this row when converting day-off → shift; only remove other day-off rows.
            $this->clearTimeOffForDay(
                $conn,
                (int) $employee->id,
                $data['scheduled_date'],
                exceptId: $wasTimeOff ? (int) $entry->id : null,
            );
            $this->cancelApprovedTimeOffRequestsForDay(
                $conn,
                (int) $employee->id,
                $data['scheduled_date'],
                $createdBy,
            );
        } else {
            $this->clearShiftsForDay($conn, (int) $employee->id, $data['scheduled_date'], exceptId: (int) $entry->id);
        }

        $entry->fill($this->scheduleEntryAttributes($data, $employee));
        $entry->save();

        $this->syncTimeOffLeaveRecord($conn, $entry, $data, $employee, $createdBy);

        // Keep the sick-leave record aligned with the shift's hours when a sick-called-out shift is edited.
        if ($entry->entry_type === EmployeeScheduleShift::TYPE_SHIFT
            && $entry->status === EmployeeScheduleShift::STATUS_SICK_CALL_OUT) {
            $this->applySickCallOutLeave($conn, $entry, $createdBy);
            $entry->save();
        }

        $message = $data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF
            ? 'Day off updated.'
            : 'Shift updated.';

        return $this->redirectBack($request, $message);
    }

    public function destroyShift(Request $request, int $scheduleShift): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->findOrFail($scheduleShift);
        $wasTimeOff = $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF;
        $employeeId = (int) $entry->employee_id;
        $scheduledDate = $entry->scheduled_date instanceof \Carbon\CarbonInterface
            ? $entry->scheduled_date->toDateString()
            : (string) $entry->scheduled_date;

        /** @var OrganizationPortalUser|null $portalUser */
        $portalUser = $request->user('portal');
        $reviewedBy = $portalUser?->name ?: $portalUser?->email;

        $this->deletePendingLeaveRecord($conn, $entry);
        $entry->delete();

        if ($wasTimeOff) {
            $this->cancelApprovedTimeOffRequestsForDay($conn, $employeeId, $scheduledDate, $reviewedBy);
        }

        return $this->redirectBack($request, $wasTimeOff
            ? 'Day off removed from the schedule.'
            : 'Shift removed from the schedule.');
    }

    public function markShiftStatus(Request $request, int $scheduleShift): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];

        $data = $request->validate([
            'status' => ['nullable', Rule::in([
                EmployeeScheduleShift::STATUS_SICK_CALL_OUT,
                EmployeeScheduleShift::STATUS_NO_SHOW,
            ])],
        ]);

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->findOrFail($scheduleShift);

        if ($entry->entry_type !== EmployeeScheduleShift::TYPE_SHIFT) {
            throw ValidationException::withMessages([
                'status' => 'Only scheduled shifts can be marked.',
            ]);
        }

        $status = $data['status'] ?? null;
        $entry->status = $status;

        /** @var OrganizationPortalUser|null $portalUser */
        $portalUser = $request->user('portal');
        $createdBy = $portalUser?->name ?: $portalUser?->email;

        if ($status === EmployeeScheduleShift::STATUS_SICK_CALL_OUT) {
            $this->applySickCallOutLeave($conn, $entry, $createdBy);
        } else {
            // No show (or cleared) is simply unpaid — drop any sick-leave record we created.
            $this->deletePendingLeaveRecord($conn, $entry);
            $entry->leave_record_id = null;
        }

        $entry->save();

        $message = $status === null
            ? 'Shift status cleared.'
            : sprintf('Shift marked as %s.', strtolower((string) EmployeeScheduleShift::statusLabel($status)));

        return $this->redirectBack($request, $message);
    }

    public function fillFromAssignments(Request $request): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];
        $weekStart = $context['weekStart'];

        $employees = $this->filteredEmployeesQuery($request, $conn)->with(['assignedShift', 'assignmentShifts.shiftTemplate'])->get();
        $weekEnd = $weekStart->copy()->addDays(6)->toDateString();

        $existingEntries = EmployeeScheduleShift::on($conn)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd])
            ->get();

        $result = AdminWeeklySchedule::fillWeekFromAssignments($conn, $employees, $weekStart, $existingEntries);
        $created = $result['created'];
        $updated = $result['updated'];

        if ($created > 0 && $updated > 0) {
            $message = sprintf(
                'Added %d shift block(s) and updated timing on %d existing block(s) from work assignments.',
                $created,
                $updated,
            );
        } elseif ($created > 0) {
            $message = sprintf('Added %d shift block(s) from work assignments.', $created);
        } elseif ($updated > 0) {
            $message = sprintf('Updated timing on %d existing shift block(s) from work assignments.', $updated);
        } else {
            $message = 'No empty days were found to fill from assignments.';
        }

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
            ->with(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate'])
            ->get();

        $scheduleEntries = EmployeeScheduleShift::on($conn)
            ->with(['shiftTemplate', 'jobTitle', 'department', 'workLocation', 'leaveType', 'leaveRecord'])
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $schedule = AdminWeeklySchedule::buildSchedule($employees, $weekStart, $scheduleEntries);

        $pendingTimeOffRequests = TimeOffRequest::on($conn)
            ->where('status', TimeOffRequest::STATUS_PENDING)
            ->with('employee')
            ->orderBy('requested_date')
            ->orderBy('id')
            ->get();

        $openTimeOffRequest = null;
        $openRequestId = $request->query('open_time_off_request');
        if (is_string($openRequestId) && ctype_digit($openRequestId)) {
            /** @var TimeOffRequest|null $req */
            $req = TimeOffRequest::on($conn)->with('employee')->find((int) $openRequestId);
            if ($req !== null
                && $req->status === TimeOffRequest::STATUS_PENDING
                && $req->employee !== null) {
                $openTimeOffRequest = [
                    'id' => $req->id,
                    'requested_date' => $req->requested_date?->toDateString(),
                    'employee_public_id' => $req->employee->public_id,
                    'employee_name' => $req->employee->full_legal_name ?: $req->employee->email,
                    'reason' => $req->reason,
                ];
            }
        }

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
            'pendingTimeOffRequests' => $pendingTimeOffRequests,
            'openTimeOffRequest' => $openTimeOffRequest,
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'shiftTemplates' => Shift::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'leaveBalances' => AdminWeeklySchedule::leaveBalancesForEmployees($conn, $employees),
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
            'leave_type_id' => ['nullable', 'integer'],
            'leave_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'required_with:leave_type_id'],
            'time_off_request_id' => ['nullable', 'integer'],
        ]);

        /** @var Employee $employee */
        $employee = Employee::on($conn)->where('public_id', $data['employee_public_id'])->firstOrFail();

        if (($employee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'employee_public_id' => 'Schedule entries can only be managed for active employees.',
            ]);
        }

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $this->assertBelongsToTenant($conn, 'shifts', $data['shift_id'] ?? null);
            $this->assertBelongsToTenant($conn, 'work_locations', $data['work_location_id'] ?? null);
        } elseif (! empty($data['leave_type_id'])) {
            $leaveTypeId = (int) $data['leave_type_id'];

            $isActiveType = LeaveType::on($conn)
                ->where('id', $leaveTypeId)
                ->where('is_active', true)
                ->exists();

            $isEntitled = EmployeeLeaveEntitlement::on($conn)
                ->where('employee_id', $employee->id)
                ->where('leave_type_id', $leaveTypeId)
                ->exists();

            if (! $isActiveType || ! $isEntitled) {
                throw ValidationException::withMessages([
                    'leave_type_id' => 'This employee is not entitled to the selected leave type.',
                ]);
            }
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
                'leave_type_id' => ! empty($data['leave_type_id']) ? (int) $data['leave_type_id'] : null,
            ];
        } else {
            $attributes = [
                ...$attributes,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'shift_id' => $data['shift_id'],
                'work_location_id' => $data['work_location_id'],
                'notes' => null,
                'leave_type_id' => null,
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

    private function clearTimeOffForDay(
        string $conn,
        int $employeeId,
        string $scheduledDate,
        ?int $exceptId = null,
    ): void {
        $query = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employeeId)
            ->where('scheduled_date', $scheduledDate)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        $entries = $query->get();

        foreach ($entries as $entry) {
            $this->deletePendingLeaveRecord($conn, $entry);
            $entry->delete();
        }
    }

    /**
     * When an approved day off is removed or replaced with a shift, the employee must not keep
     * seeing "Approved" — mark matching requests cancelled with an explanatory note.
     */
    private function cancelApprovedTimeOffRequestsForDay(
        string $conn,
        int $employeeId,
        string $scheduledDate,
        ?string $reviewedBy = null,
    ): void {
        $requests = TimeOffRequest::on($conn)
            ->where('employee_id', $employeeId)
            ->whereDate('requested_date', $scheduledDate)
            ->where('status', TimeOffRequest::STATUS_APPROVED)
            ->get();

        foreach ($requests as $req) {
            $req->fill([
                'status' => TimeOffRequest::STATUS_CANCELLED,
                'decision_note' => 'Your day off was removed and a shift was scheduled instead.',
                'reviewed_by' => $reviewedBy ?: $req->reviewed_by,
                'reviewed_at' => now(),
                'schedule_shift_id' => null,
                'leave_record_id' => null,
            ])->save();
        }
    }

    /**
     * Create/update/remove the leave record tied to a day-off entry so leave balances stay in sync.
     *
     * @param  array<string, mixed>  $data
     */
    private function syncTimeOffLeaveRecord(string $conn, EmployeeScheduleShift $entry, array $data, Employee $employee, ?string $createdBy): void
    {
        $isTimeOff = $entry->entry_type === EmployeeScheduleShift::TYPE_TIME_OFF;
        $leaveTypeId = $isTimeOff && ! empty($data['leave_type_id']) ? (int) $data['leave_type_id'] : null;

        $existingRecord = $entry->leave_record_id !== null
            ? EmployeeLeaveRecord::on($conn)->find($entry->leave_record_id)
            : null;

        if ($leaveTypeId === null) {
            if ($existingRecord !== null && $existingRecord->status === EmployeeLeaveRecord::STATUS_PENDING) {
                $existingRecord->delete();
            }
            if ($entry->leave_record_id !== null) {
                $entry->leave_record_id = null;
                $entry->save();
            }

            return;
        }

        /** @var LeaveType|null $leaveType */
        $leaveType = LeaveType::on($conn)->find($leaveTypeId);
        if ($leaveType === null) {
            return;
        }

        $isPaid = (bool) $leaveType->is_paid;
        $rates = PayrollEmployeeRates::forEmployee($conn, $employee);
        $ordinary = PayrollEmployeeRates::ordinaryHourlyRate($rates);

        $attributes = [
            'employee_id' => $employee->id,
            'leave_type' => $leaveType->code,
            'is_paid' => $isPaid,
            'leave_date' => $data['scheduled_date'],
            'hours' => round((float) ($data['leave_hours'] ?? 0), 2),
            'hourly_rate' => $isPaid && $ordinary > 0 ? $ordinary : null,
            'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
        ];

        if ($existingRecord !== null && $existingRecord->status === EmployeeLeaveRecord::STATUS_PENDING) {
            $existingRecord->fill($attributes)->save();
            $recordId = (int) $existingRecord->id;
        } else {
            /** @var EmployeeLeaveRecord $record */
            $record = EmployeeLeaveRecord::on($conn)->create([
                ...$attributes,
                'status' => EmployeeLeaveRecord::STATUS_PENDING,
                'created_by' => $createdBy,
            ]);
            $recordId = (int) $record->id;
        }

        if ((int) $entry->leave_record_id !== $recordId) {
            $entry->leave_record_id = $recordId;
            $entry->save();
        }
    }

    private function deletePendingLeaveRecord(string $conn, EmployeeScheduleShift $entry): void
    {
        if ($entry->leave_record_id === null) {
            return;
        }

        $record = EmployeeLeaveRecord::on($conn)->find($entry->leave_record_id);
        if ($record !== null && $record->status === EmployeeLeaveRecord::STATUS_PENDING) {
            $record->delete();
        }
    }

    /**
     * Marking a shift as a sick call out records sick leave for that day so payroll pays it
     * and the balance is deducted — paid only when the employee is entitled to sick leave,
     * otherwise it is tracked as unpaid. Hours come from the scheduled shift duration.
     */
    private function applySickCallOutLeave(string $conn, EmployeeScheduleShift $entry, ?string $createdBy): void
    {
        /** @var Employee|null $employee */
        $employee = Employee::on($conn)->find($entry->employee_id);
        $hours = $this->shiftDurationHours($entry);

        /** @var LeaveType|null $sickType */
        $sickType = LeaveType::on($conn)
            ->where('code', EmployeeLeaveRecord::TYPE_SICK)
            ->where('is_active', true)
            ->first();

        if ($employee === null || $sickType === null || $hours <= 0 || $entry->scheduled_date === null) {
            $this->deletePendingLeaveRecord($conn, $entry);
            $entry->leave_record_id = null;

            return;
        }

        $isEntitled = EmployeeLeaveEntitlement::on($conn)
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $sickType->id)
            ->exists();

        $isPaid = $isEntitled && (bool) $sickType->is_paid;

        $rates = PayrollEmployeeRates::forEmployee($conn, $employee);
        $ordinary = PayrollEmployeeRates::ordinaryHourlyRate($rates);

        $attributes = [
            'employee_id' => $employee->id,
            'leave_type' => EmployeeLeaveRecord::TYPE_SICK,
            'is_paid' => $isPaid,
            'leave_date' => $entry->scheduled_date->toDateString(),
            'hours' => round($hours, 2),
            'hourly_rate' => $isPaid && $ordinary > 0 ? $ordinary : null,
            'notes' => 'Sick call out',
        ];

        $existing = $entry->leave_record_id !== null
            ? EmployeeLeaveRecord::on($conn)->find($entry->leave_record_id)
            : null;

        if ($existing !== null && $existing->status === EmployeeLeaveRecord::STATUS_PENDING) {
            $existing->fill($attributes)->save();
            $entry->leave_record_id = (int) $existing->id;

            return;
        }

        /** @var EmployeeLeaveRecord $record */
        $record = EmployeeLeaveRecord::on($conn)->create([
            ...$attributes,
            'status' => EmployeeLeaveRecord::STATUS_PENDING,
            'created_by' => $createdBy,
        ]);
        $entry->leave_record_id = (int) $record->id;
    }

    private function shiftDurationHours(EmployeeScheduleShift $entry): float
    {
        $start = $entry->start_time;
        $end = $entry->end_time;

        if (! $start instanceof \Carbon\CarbonInterface || ! $end instanceof \Carbon\CarbonInterface) {
            return 0.0;
        }

        $startMinutes = ((int) $start->format('H') * 60) + (int) $start->format('i');
        $endMinutes = ((int) $end->format('H') * 60) + (int) $end->format('i');

        if ($endMinutes <= $startMinutes) {
            $endMinutes += 24 * 60;
        }

        return round(($endMinutes - $startMinutes) / 60, 2);
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
