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
use App\Support\AdminTimeOffRequestReview;
use App\Support\AdminWeeklySchedule;
use App\Support\PayrollEmployeeRates;
use App\Support\WorkforceShifts;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
        $employees = $this->employeesForShiftPayload($data, $conn);

        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $reviewedBy = $portalUser->name ?: $portalUser->email;

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->resolveScheduleShiftTemplate($data, $conn);
            $dates = AdminWeeklySchedule::recurrenceDates(
                $data['scheduled_date'],
                (string) ($data['recurrence'] ?? 'never'),
                $data['shift_days'] ?? null,
                $data['recurrence_until'] ?? null,
            );

            $created = 0;
            foreach ($employees as $employee) {
                $seriesData = $this->withRecurrenceSeries($data);
                $created += $this->createRecurringShiftEntries(
                    $conn,
                    $employee,
                    $seriesData,
                    $dates,
                    $portalUser->name,
                    $reviewedBy,
                );
            }

            $employeeCount = $employees->count();
            if ($employeeCount > 1) {
                $message = sprintf(
                    '%d shift(s) saved across %d employees.',
                    $created,
                    $employeeCount,
                );
            } else {
                $message = $created === 1
                    ? 'Shift saved to the weekly schedule.'
                    : sprintf('%d shifts saved to the weekly schedule.', $created);
            }

            return $this->redirectBack($request, $message);
        }

        $created = 0;
        foreach ($employees as $index => $employee) {
            $this->assertLeaveTypeAllowedForEmployee($conn, $employee, $data);

            $this->clearShiftsForDay($conn, (int) $employee->id, $data['scheduled_date']);
            $this->clearTimeOffForDay($conn, (int) $employee->id, $data['scheduled_date']);

            /** @var EmployeeScheduleShift $entry */
            $entry = EmployeeScheduleShift::on($conn)->create($this->scheduleEntryAttributes($data, $employee, $portalUser->name));
            $this->syncTimeOffLeaveRecord($conn, $entry, $data, $employee, $reviewedBy);

            if ($index === 0 && ! empty($data['time_off_request_id'])) {
                $this->approveTimeOffRequest(
                    $conn,
                    (int) $data['time_off_request_id'],
                    $employee,
                    $entry,
                    $reviewedBy,
                );
            }

            $created++;
        }

        $message = $created === 1
            ? 'Day off saved to the weekly schedule.'
            : sprintf('%d day-off entries saved to the weekly schedule.', $created);

        return $this->redirectBack($request, $message);
    }

    public function approvePendingTimeOffRequest(Request $request, int $timeOffRequest): RedirectResponse
    {
        /** @var OrganizationPortalUser $portalUser */
        $portalUser = $request->user('portal');
        $company = $portalUser->company()->firstOrFail();
        $conn = $company->tenant_connection;
        $reviewedBy = $portalUser->name ?: $portalUser->email;

        $data = $request->validate([
            'leave_type_id' => ['nullable', 'integer'],
            'leave_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'required_with:leave_type_id'],
        ]);

        /** @var TimeOffRequest $req */
        $req = TimeOffRequest::on($conn)->with('employee')->findOrFail($timeOffRequest);

        if ($req->status !== TimeOffRequest::STATUS_PENDING) {
            return $this->redirectToDashboard('This time-off request is no longer pending.');
        }

        /** @var Employee|null $employee */
        $employee = $req->employee;
        if ($employee === null || ($employee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'time_off_request' => 'Time-off requests can only be approved for active employees.',
            ]);
        }

        $scheduledDate = $req->requested_date?->toDateString();
        if ($scheduledDate === null || $scheduledDate === '') {
            throw ValidationException::withMessages([
                'time_off_request' => 'This time-off request has no valid date.',
            ]);
        }

        $leaveTypeId = ! empty($data['leave_type_id']) ? (int) $data['leave_type_id'] : null;
        if ($leaveTypeId !== null) {
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

        $payload = AdminTimeOffRequestReview::dayOffPayload(
            $req,
            $employee,
            $leaveTypeId,
            $leaveTypeId !== null ? (float) $data['leave_hours'] : null,
        );

        $this->clearShiftsForDay($conn, (int) $employee->id, $scheduledDate);
        $this->clearTimeOffForDay($conn, (int) $employee->id, $scheduledDate);

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->create(
            $this->scheduleEntryAttributes($payload, $employee, $portalUser->name)
        );
        $this->syncTimeOffLeaveRecord($conn, $entry, $payload, $employee, $reviewedBy);
        $this->approveTimeOffRequest($conn, (int) $req->id, $employee, $entry, $reviewedBy);

        return $this->redirectToDashboard('Time-off request approved.');
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

            $req->fill(AdminTimeOffRequestReview::rejectAttributes(
                $note,
                $portalUser->name ?: $portalUser->email,
            ))->save();
        }

        return $this->redirectToDashboard('Time-off request rejected.');
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

        $req->fill(AdminTimeOffRequestReview::approveAttributes($reviewedBy, $entry))->save();
    }

    private function redirectToDashboard(string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.dashboard')
            ->with('status', $message);
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
            $data = $this->resolveScheduleShiftTemplate($data, $conn);
            $originalDate = $entry->scheduled_date instanceof CarbonInterface
                ? $entry->scheduled_date->toDateString()
                : (string) $entry->scheduled_date;
            if ($data['scheduled_date'] !== $originalDate) {
                $this->assertDateAvailable(
                    $conn,
                    (int) $employee->id,
                    $data['scheduled_date'],
                    exceptId: (int) $entry->id,
                );
            }
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

        $previousFingerprint = $this->shiftSeriesFingerprint($entry);
        $previousSeriesId = is_string($entry->recurrence_series_id) && $entry->recurrence_series_id !== ''
            ? $entry->recurrence_series_id
            : null;
        $previousStarts = $entry->recurrence_starts instanceof CarbonInterface
            ? $entry->recurrence_starts->toDateString()
            : (is_string($entry->recurrence_starts) ? $entry->recurrence_starts : null);

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $data = $this->withRecurrenceSeries($data, $previousSeriesId, $previousStarts);
        }

        $entry->fill($this->scheduleEntryAttributes($data, $employee));

        // Day-off reason must not carry over when converting to a scheduled shift unless the form sent notes.
        if ($wasTimeOff && $data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $entry->status = null;
        }

        $entry->save();

        $this->syncTimeOffLeaveRecord($conn, $entry, $data, $employee, $createdBy);

        // Keep the sick-leave record aligned with the shift's hours when a sick-called-out shift is edited.
        if ($entry->entry_type === EmployeeScheduleShift::TYPE_SHIFT
            && $entry->status === EmployeeScheduleShift::STATUS_SICK_CALL_OUT) {
            $this->applySickCallOutLeave($conn, $entry, $createdBy);
            $entry->save();
        }

        $extraCreated = 0;
        $extraUpdated = 0;
        $extraDeleted = 0;
        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            [$extraCreated, $extraUpdated, $extraDeleted] = $this->applyRecurrenceOnUpdate(
                $conn,
                $entry,
                $employee,
                $data,
                $previousFingerprint,
                $previousSeriesId,
                $createdBy,
            );
        }

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_TIME_OFF) {
            $message = 'Day off updated.';
        } elseif ($extraCreated > 0 || $extraUpdated > 0 || $extraDeleted > 0) {
            $parts = ['Shift updated'];
            if ($extraUpdated > 0) {
                $parts[] = sprintf('%d related shift(s) updated', $extraUpdated);
            }
            if ($extraCreated > 0) {
                $parts[] = sprintf('%d shift(s) added', $extraCreated);
            }
            if ($extraDeleted > 0) {
                $parts[] = sprintf('%d shift(s) removed', $extraDeleted);
            }
            $message = implode('; ', $parts).'.';
        } else {
            $message = 'Shift updated.';
        }

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
        $scheduledDate = $entry->scheduled_date instanceof CarbonInterface
            ? $entry->scheduled_date->toDateString()
            : (string) $entry->scheduled_date;

        /** @var OrganizationPortalUser|null $portalUser */
        $portalUser = $request->user('portal');
        $reviewedBy = $portalUser?->name ?: $portalUser?->email;

        $this->deletePendingLeaveRecord($conn, $entry);
        $this->detachShiftCoverOnDelete($conn, $entry);
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
            'notes' => ['nullable', 'string', 'max:500'],
            'cover_action' => ['nullable', Rule::in(EmployeeScheduleShift::coverActionValues())],
            'cover_employee_public_id' => ['nullable', 'string'],
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
        $entry->notes = $status !== null && isset($data['notes']) && trim((string) $data['notes']) !== ''
            ? trim((string) $data['notes'])
            : null;

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

        if ($status === null) {
            $this->clearShiftCover($conn, $entry);
        } else {
            $coverAction = $data['cover_action'] ?? null;
            if ($coverAction === null || $coverAction === '') {
                throw ValidationException::withMessages([
                    'cover_action' => 'Choose how this shift should be covered.',
                ]);
            }

            $this->applyShiftCover(
                $conn,
                $entry,
                $coverAction,
                $this->coverEmployeeForAction($conn, $entry, $coverAction, $data['cover_employee_public_id'] ?? null),
                $createdBy,
            );
        }

        $entry->save();

        $message = $status === null
            ? 'Shift status cleared.'
            : sprintf('Shift marked as %s.', strtolower((string) EmployeeScheduleShift::statusLabel($status)));

        return $this->redirectBack($request, $message);
    }

    public function assignShiftCover(Request $request, int $scheduleShift): RedirectResponse
    {
        $context = $this->scheduleContext($request);
        $conn = $context['conn'];

        $data = $request->validate([
            'cover_employee_public_id' => ['required', 'string'],
        ]);

        /** @var EmployeeScheduleShift $entry */
        $entry = EmployeeScheduleShift::on($conn)->findOrFail($scheduleShift);

        if ($entry->entry_type !== EmployeeScheduleShift::TYPE_SHIFT) {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => 'Only scheduled shifts can be assigned cover.',
            ]);
        }

        if ($entry->status === null || $entry->status === '') {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => 'Mark the original shift as sick call out or no show before assigning cover.',
            ]);
        }

        /** @var OrganizationPortalUser|null $portalUser */
        $portalUser = $request->user('portal');
        $createdBy = $portalUser?->name ?: $portalUser?->email;

        $this->applyShiftCover(
            $conn,
            $entry,
            EmployeeScheduleShift::COVER_ACTION_ASSIGN_EMPLOYEE,
            $this->coverEmployeeForAction(
                $conn,
                $entry,
                EmployeeScheduleShift::COVER_ACTION_ASSIGN_EMPLOYEE,
                $data['cover_employee_public_id'],
            ),
            $createdBy,
        );
        $entry->save();

        return $this->redirectBack($request, 'Shift assigned to the selected employee.');
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
            ->with(['assignedDepartment', 'assignedJobTitle', 'jobTitles', 'workLocation', 'assignedShift', 'assignmentShifts.shiftTemplate'])
            ->get();

        $scheduleRelations = [
            'shiftTemplate',
            'jobTitle',
            'department',
            'workLocation',
            'leaveType',
            'leaveRecord',
            'employee',
            'originalEmployee',
            'coveredFromShift',
            'coveringShift.employee',
        ];

        $scheduleEntries = EmployeeScheduleShift::on($conn)
            ->with($scheduleRelations)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $unassignedEntries = EmployeeScheduleShift::on($conn)
            ->with($scheduleRelations)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
            ->whereIn('cover_status', [
                EmployeeScheduleShift::COVER_LEAVE_UNCOVERED,
                EmployeeScheduleShift::COVER_UNASSIGNED,
            ])
            ->whereBetween('scheduled_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->orderBy('scheduled_date')
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
            'unassignedRows' => AdminWeeklySchedule::uncoveredSchedule($unassignedEntries, $weekStart)['rows'],
            'departments' => Department::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'workLocations' => WorkLocation::on($conn)->where('is_active', true)->orderBy('name')->get(),
            'shiftTemplates' => ($shiftTemplates = Shift::on($conn)->where('is_active', true)->orderBy('name')->get()),
            'shiftCatalog' => WorkforceShifts::catalogForConnection($conn, $shiftTemplates),
            'leaveBalances' => AdminWeeklySchedule::leaveBalancesForEmployees($conn, $employees),
            'employees' => Employee::on($conn)
                ->where('employment_status', 'active')
                ->with(['assignedJobTitle', 'jobTitles'])
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
            'employee_public_ids' => ['nullable', 'array', 'max:100'],
            'employee_public_ids.*' => ['string'],
            'scheduled_date' => ['required', 'date'],
            'entry_type' => ['required', Rule::in([EmployeeScheduleShift::TYPE_SHIFT, EmployeeScheduleShift::TYPE_TIME_OFF])],
            'shift_id' => ['nullable', 'integer'],
            'work_location_id' => ['nullable', 'integer', 'required_if:entry_type,'.EmployeeScheduleShift::TYPE_SHIFT],
            'job_title_id' => ['nullable', 'integer'],
            'start_time' => ['nullable', 'date_format:H:i', 'required_if:entry_type,'.EmployeeScheduleShift::TYPE_SHIFT],
            'end_time' => ['nullable', 'date_format:H:i', 'required_if:entry_type,'.EmployeeScheduleShift::TYPE_SHIFT],
            'notes' => ['nullable', 'string', 'max:500'],
            'shift_breaks' => ['nullable', 'array', 'max:8'],
            'shift_breaks.*.label' => ['nullable', 'string', 'max:80'],
            'shift_breaks.*.minutes' => ['nullable', 'integer', 'min:1', 'max:480'],
            'shift_breaks.*.paid' => ['nullable'],
            'recurrence' => ['nullable', Rule::in([
                'never',
                'every_week',
                'every_2_weeks',
                'every_3_weeks',
                'every_4_weeks',
                'every_5_weeks',
                'every_6_weeks',
                'every_7_weeks',
                'every_8_weeks',
                'weekly',
            ])],
            'recurrence_series_id' => ['nullable', 'string', 'max:36'],
            'recurrence_starts' => ['nullable', 'date'],
            'shift_days' => ['nullable', 'array'],
            'shift_days.*' => ['string', Rule::in(WorkforceShifts::allowedDays())],
            'recurrence_until' => ['nullable', 'date'],
            'leave_type_id' => ['nullable', 'integer'],
            'leave_hours' => ['nullable', 'numeric', 'min:0.25', 'max:24', 'required_with:leave_type_id'],
            'time_off_request_id' => ['nullable', 'integer'],
        ]);

        $employees = $this->employeesForShiftPayload($data, $conn);

        if ($data['entry_type'] === EmployeeScheduleShift::TYPE_SHIFT) {
            $this->assertBelongsToTenant($conn, 'work_locations', $data['work_location_id'] ?? null);
            foreach ($employees as $employee) {
                $this->assertJobTitleAllowedForEmployee($conn, $employee, $data['job_title_id'] ?? null);
            }
        } else {
            foreach ($employees as $employee) {
                $this->assertLeaveTypeAllowedForEmployee($conn, $employee, $data);
            }
        }

        return $data;
    }

    private function assertJobTitleAllowedForEmployee(string $conn, Employee $employee, mixed $jobTitleId): void
    {
        if ($jobTitleId === null || $jobTitleId === '') {
            return;
        }

        $id = (int) $jobTitleId;
        if ($id <= 0) {
            return;
        }

        $this->assertBelongsToTenant($conn, 'job_titles', $id);

        $employee->loadMissing('jobTitles');
        $allowed = $employee->jobTitles->contains(fn ($jt) => (int) $jt->id === $id)
            || (int) $employee->job_title_id === $id;

        if (! $allowed) {
            throw ValidationException::withMessages([
                'job_title_id' => 'Pick a job title assigned to this employee.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Employee>
     */
    private function employeesForShiftPayload(array $data, string $conn)
    {
        $publicIds = collect($data['employee_public_ids'] ?? [])
            ->push($data['employee_public_id'] ?? '')
            ->map(static fn ($id): string => trim((string) $id))
            ->filter(static fn (string $id): bool => $id !== '')
            ->unique()
            ->values();

        if ($publicIds->isEmpty()) {
            throw ValidationException::withMessages([
                'employee_public_id' => 'Select at least one employee.',
            ]);
        }

        $employees = Employee::on($conn)
            ->whereIn('public_id', $publicIds->all())
            ->get()
            ->keyBy('public_id');

        $ordered = $publicIds->map(function (string $publicId) use ($employees) {
            /** @var Employee|null $employee */
            $employee = $employees->get($publicId);

            if ($employee === null) {
                throw ValidationException::withMessages([
                    'employee_public_ids' => 'One or more selected employees could not be found.',
                ]);
            }

            if (($employee->employment_status ?? '') !== 'active') {
                throw ValidationException::withMessages([
                    'employee_public_ids' => 'Schedule entries can only be managed for active employees.',
                ]);
            }

            return $employee;
        });

        return $ordered->values();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertLeaveTypeAllowedForEmployee(string $conn, Employee $employee, array $data): void
    {
        if (($data['entry_type'] ?? '') !== EmployeeScheduleShift::TYPE_TIME_OFF || empty($data['leave_type_id'])) {
            return;
        }

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
            $name = AdminWeeklySchedule::employeeDisplayName($employee);
            throw ValidationException::withMessages([
                'leave_type_id' => $name.' is not entitled to the selected leave type.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withRecurrenceSeries(array $data, ?string $existingSeriesId = null, ?string $existingStarts = null): array
    {
        $mode = strtolower(trim((string) ($data['recurrence'] ?? 'never')));
        if ($mode === '' || $mode === 'never') {
            $data['recurrence_series_id'] = null;
            $data['recurrence_starts'] = null;

            return $data;
        }

        if (is_string($existingSeriesId) && $existingSeriesId !== '') {
            $data['recurrence_series_id'] = $existingSeriesId;
        } elseif (is_string($data['recurrence_series_id'] ?? null) && $data['recurrence_series_id'] !== '') {
            // keep posted series id
        } else {
            $data['recurrence_series_id'] = (string) \Illuminate\Support\Str::uuid();
        }

        $postedStarts = is_string($data['recurrence_starts'] ?? null) ? trim((string) $data['recurrence_starts']) : '';
        if ($postedStarts !== '') {
            $data['recurrence_starts'] = $postedStarts;
        } elseif (is_string($existingStarts) && $existingStarts !== '') {
            $data['recurrence_starts'] = $existingStarts;
        } else {
            $data['recurrence_starts'] = $data['scheduled_date'] ?? null;
        }

        return $data;
    }

    /**
     * Apply repeat-rule edits across the whole series: update matching rows, create missing
     * dates, and remove series members that no longer fall in the pattern.
     *
     * @param  array<string, mixed>  $data
     * @param  array{start_time: ?string, end_time: ?string, shift_id: ?int, work_location_id: ?int, job_title_id: ?int}  $previousFingerprint
     * @return array{0: int, 1: int, 2: int} created, updated, deleted
     */
    private function applyRecurrenceOnUpdate(
        string $conn,
        EmployeeScheduleShift $entry,
        Employee $employee,
        array $data,
        array $previousFingerprint,
        ?string $previousSeriesId,
        ?string $createdBy,
    ): array {
        $mode = strtolower(trim((string) ($data['recurrence'] ?? 'never')));
        $currentDate = $entry->scheduled_date instanceof CarbonInterface
            ? $entry->scheduled_date->toDateString()
            : (string) $entry->scheduled_date;
        $currentId = (int) $entry->id;

        if ($mode === '' || $mode === 'never') {
            // "Does not repeat" only affects this row; leave other series members alone.
            return [0, 0, 0];
        }

        $seriesStart = is_string($data['recurrence_starts'] ?? null) && $data['recurrence_starts'] !== ''
            ? $data['recurrence_starts']
            : (string) $data['scheduled_date'];

        $days = WorkforceShifts::normalizeDays($data['shift_days'] ?? null) ?? [];
        if ($days === []) {
            throw ValidationException::withMessages([
                'shift_days' => 'Choose at least one day for a repeating shift.',
            ]);
        }
        $data['shift_days'] = $days;

        $dates = AdminWeeklySchedule::recurrenceDates(
            $seriesStart,
            $mode,
            $days,
            $data['recurrence_until'] ?? null,
        );
        $dateSet = array_fill_keys($dates, true);

        // Always prefer the series id from before this save so we can find and clean old members.
        $seriesId = $previousSeriesId
            ?? (is_string($data['recurrence_series_id'] ?? null) && $data['recurrence_series_id'] !== ''
                ? $data['recurrence_series_id']
                : null);
        if ($seriesId !== null) {
            $data['recurrence_series_id'] = $seriesId;
        }

        $seriesMembersQuery = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employee->id)
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT);

        if ($seriesId !== null) {
            $seriesMembersQuery->where('recurrence_series_id', $seriesId);
        } else {
            $this->applyFingerprintConstraints($seriesMembersQuery, $previousFingerprint);
        }

        $seriesMembers = $seriesMembersQuery->get();

        $occupiedByDate = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employee->id)
            ->whereIn('scheduled_date', $dates === [] ? [$currentDate] : $dates)
            ->whereIn('entry_type', [EmployeeScheduleShift::TYPE_SHIFT, EmployeeScheduleShift::TYPE_TIME_OFF])
            ->get()
            ->groupBy(static function (EmployeeScheduleShift $row): string {
                return $row->scheduled_date instanceof CarbonInterface
                    ? $row->scheduled_date->toDateString()
                    : (string) $row->scheduled_date;
            });

        $existingPlanInput = [];
        foreach ($dates as $date) {
            if ($date === $currentDate) {
                continue;
            }
            /** @var \Illuminate\Support\Collection<int, EmployeeScheduleShift> $dayEntries */
            $dayEntries = $occupiedByDate->get($date, collect());
            $existingPlanInput[$date] = $dayEntries->map(function (EmployeeScheduleShift $row) use ($seriesId, $previousFingerprint): array {
                $sameSeries = ($seriesId !== null
                        && is_string($row->recurrence_series_id)
                        && $row->recurrence_series_id === $seriesId)
                    || $this->shiftMatchesSeriesFingerprint($row, $previousFingerprint);

                return [
                    'id' => (int) $row->id,
                    'entry_type' => (string) $row->entry_type,
                    'matches_series' => $row->entry_type === EmployeeScheduleShift::TYPE_SHIFT && $sameSeries,
                ];
            })->all();
        }

        $plan = AdminWeeklySchedule::planRecurrenceEditActions($currentDate, $dates, $existingPlanInput);

        $created = 0;
        $updated = 0;
        $deleted = 0;
        $now = now();
        $insertRows = [];
        $keptIds = [];

        // Keep the edited row only when its date is still part of the repeat pattern.
        if (isset($dateSet[$currentDate])) {
            $keptIds[$currentId] = true;
            // Re-save with the canonical series id / days after validation above.
            $entry->fill($this->scheduleEntryAttributes(
                [...$data, 'scheduled_date' => $currentDate],
                $employee,
            ))->save();
        }

        foreach ($plan as $step) {
            if ($step['action'] === 'update' && $step['entry_id'] !== null) {
                /** @var EmployeeScheduleShift|null $match */
                $match = $occupiedByDate
                    ->get($step['date'], collect())
                    ->firstWhere('id', $step['entry_id']);

                if (! $match instanceof EmployeeScheduleShift) {
                    continue;
                }

                $match->fill($this->scheduleEntryAttributes(
                    [...$data, 'scheduled_date' => $step['date']],
                    $employee,
                ));
                $match->save();
                $keptIds[(int) $match->id] = true;
                $updated++;

                continue;
            }

            if ($step['action'] !== 'create') {
                continue;
            }

            $attributes = $this->scheduleEntryAttributes(
                [...$data, 'scheduled_date' => $step['date']],
                $employee,
                $createdBy,
            );
            $insertRows[] = [
                ...$this->attributesForBulkInsert($attributes),
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $created++;
        }

        foreach (array_chunk($insertRows, 250) as $chunk) {
            EmployeeScheduleShift::on($conn)->insert($chunk);
        }

        foreach ($seriesMembers as $member) {
            $memberId = (int) $member->id;
            if (isset($keptIds[$memberId])) {
                continue;
            }

            $memberDate = $member->scheduled_date instanceof CarbonInterface
                ? $member->scheduled_date->toDateString()
                : (string) $member->scheduled_date;

            // Date still belongs in the repeat pattern — keep/update instead of deleting.
            if (isset($dateSet[$memberDate])) {
                $member->fill($this->scheduleEntryAttributes(
                    [...$data, 'scheduled_date' => $memberDate],
                    $employee,
                ))->save();
                $keptIds[$memberId] = true;
                $updated++;
                continue;
            }

            $this->deletePendingLeaveRecord($conn, $member);
            $this->detachShiftCoverOnDelete($conn, $member);
            $member->delete();
            $deleted++;
        }

        // Edited day was removed from the repeat pattern — delete this occurrence too.
        if (! isset($dateSet[$currentDate]) && $entry->exists) {
            $this->deletePendingLeaveRecord($conn, $entry);
            $this->detachShiftCoverOnDelete($conn, $entry);
            $entry->delete();
            $deleted++;
        }

        return [$created, $updated, $deleted];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EmployeeScheduleShift>|\Illuminate\Database\Eloquent\Relations\Relation|\Illuminate\Database\Query\Builder  $query
     * @param  array{start_time: ?string, end_time: ?string, shift_id: ?int, work_location_id: ?int, job_title_id: ?int}  $fingerprint
     */
    private function applyFingerprintConstraints(mixed $query, array $fingerprint): void
    {
        if (($fingerprint['start_time'] ?? null) !== null) {
            $query->whereTime('start_time', $fingerprint['start_time']);
        }
        if (($fingerprint['end_time'] ?? null) !== null) {
            $query->whereTime('end_time', $fingerprint['end_time']);
        }
        if (($fingerprint['shift_id'] ?? null) !== null) {
            $query->where('shift_id', $fingerprint['shift_id']);
        } else {
            $query->whereNull('shift_id');
        }
        if (($fingerprint['work_location_id'] ?? null) !== null) {
            $query->where('work_location_id', $fingerprint['work_location_id']);
        } else {
            $query->whereNull('work_location_id');
        }
        if (($fingerprint['job_title_id'] ?? null) !== null) {
            $query->where('job_title_id', $fingerprint['job_title_id']);
        } else {
            $query->whereNull('job_title_id');
        }
    }

    /**
     * @return array{start_time: ?string, end_time: ?string, shift_id: ?int, work_location_id: ?int, job_title_id: ?int}
     */
    private function shiftSeriesFingerprint(EmployeeScheduleShift $entry): array
    {
        return [
            'start_time' => $this->normalizeHm($entry->start_time),
            'end_time' => $this->normalizeHm($entry->end_time),
            'shift_id' => $entry->shift_id !== null ? (int) $entry->shift_id : null,
            'work_location_id' => $entry->work_location_id !== null ? (int) $entry->work_location_id : null,
            'job_title_id' => $entry->job_title_id !== null ? (int) $entry->job_title_id : null,
        ];
    }

    /**
     * @param  array{start_time: ?string, end_time: ?string, shift_id: ?int, work_location_id: ?int, job_title_id: ?int}  $fingerprint
     */
    private function shiftMatchesSeriesFingerprint(EmployeeScheduleShift $entry, array $fingerprint): bool
    {
        if ($this->normalizeHm($entry->start_time) !== ($fingerprint['start_time'] ?? null)
            || $this->normalizeHm($entry->end_time) !== ($fingerprint['end_time'] ?? null)) {
            return false;
        }

        $shiftId = $entry->shift_id !== null ? (int) $entry->shift_id : null;
        $locationId = $entry->work_location_id !== null ? (int) $entry->work_location_id : null;
        $jobTitleId = $entry->job_title_id !== null ? (int) $entry->job_title_id : null;

        return $shiftId === ($fingerprint['shift_id'] ?? null)
            && $locationId === ($fingerprint['work_location_id'] ?? null)
            && $jobTitleId === ($fingerprint['job_title_id'] ?? null);
    }

    private function normalizeHm(mixed $time): ?string
    {
        if ($time instanceof CarbonInterface) {
            return $time->format('H:i');
        }

        if (is_string($time) && preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches) === 1) {
            return sprintf('%02d:%02d', (int) $matches[1], (int) $matches[2]);
        }

        return null;
    }

    /**
     * @param  list<string>  $dates
     * @param  array<string, mixed>  $data
     */
    private function createRecurringShiftEntries(
        string $conn,
        Employee $employee,
        array $data,
        array $dates,
        ?string $createdBy,
        string $reviewedBy,
    ): int {
        if ($dates === []) {
            return 0;
        }

        $firstDate = $dates[0];
        $this->clearTimeOffForDay($conn, (int) $employee->id, $firstDate);
        $this->cancelApprovedTimeOffRequestsForDay(
            $conn,
            (int) $employee->id,
            $firstDate,
            $reviewedBy,
        );

        $occupied = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employee->id)
            ->whereIn('scheduled_date', $dates)
            ->pluck('scheduled_date')
            ->map(static fn ($date) => $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date)
            ->flip()
            ->all();

        $now = now();
        $rows = [];
        foreach ($dates as $index => $date) {
            if ($index > 0 && isset($occupied[$date])) {
                continue;
            }

            $attributes = $this->scheduleEntryAttributes(
                [...$data, 'scheduled_date' => $date],
                $employee,
                $createdBy,
            );
            $rows[] = [
                ...$this->attributesForBulkInsert($attributes),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            EmployeeScheduleShift::on($conn)->insert($chunk);
        }

        return count($rows);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesForBulkInsert(array $attributes): array
    {
        if (array_key_exists('recurrence_days', $attributes)) {
            $days = $attributes['recurrence_days'];
            $attributes['recurrence_days'] = is_array($days) ? json_encode(array_values($days)) : $days;
        }

        return $attributes;
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
            'job_title_id' => ! empty($data['job_title_id'])
                ? (int) $data['job_title_id']
                : $employee->job_title_id,
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
                'recurrence_series_id' => null,
                'recurrence_mode' => null,
                'recurrence_starts' => null,
                'recurrence_until' => null,
                'recurrence_days' => null,
            ];
        } else {
            $seriesId = is_string($data['recurrence_series_id'] ?? null) && $data['recurrence_series_id'] !== ''
                ? $data['recurrence_series_id']
                : null;
            $attributes = [
                ...$attributes,
                'start_time' => $data['start_time'],
                'end_time' => $data['end_time'],
                'shift_id' => $data['shift_id'],
                'work_location_id' => $data['work_location_id'],
                'leave_type_id' => null,
                'notes' => isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
                ...AdminWeeklySchedule::recurrenceAttributesFromPayload($data, $seriesId),
            ];
        }

        if ($createdBy !== null) {
            $attributes['created_by'] = $createdBy;
        }

        return $attributes;
    }

    private function coverEmployeeForAction(
        string $conn,
        EmployeeScheduleShift $entry,
        string $coverAction,
        mixed $coverEmployeePublicId,
    ): ?Employee {
        if ($coverAction !== EmployeeScheduleShift::COVER_ACTION_ASSIGN_EMPLOYEE) {
            return null;
        }

        $publicId = is_string($coverEmployeePublicId) ? trim($coverEmployeePublicId) : '';
        if ($publicId === '') {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => 'Select an employee to cover this shift.',
            ]);
        }

        /** @var Employee|null $coverEmployee */
        $coverEmployee = Employee::on($conn)
            ->where('public_id', $publicId)
            ->first();

        if ($coverEmployee === null || ($coverEmployee->employment_status ?? '') !== 'active') {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => 'The selected employee could not be found.',
            ]);
        }

        if ((int) $coverEmployee->id === (int) $entry->employee_id) {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => 'Choose a different employee to cover this shift.',
            ]);
        }

        $scheduledDate = $entry->scheduled_date instanceof CarbonInterface
            ? $entry->scheduled_date->toDateString()
            : (string) $entry->scheduled_date;

        $hasTimeOff = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $coverEmployee->id)
            ->where('scheduled_date', $scheduledDate)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF)
            ->exists();

        if ($hasTimeOff) {
            throw ValidationException::withMessages([
                'cover_employee_public_id' => AdminWeeklySchedule::employeeDisplayName($coverEmployee).' already has a day off on this date.',
            ]);
        }

        return $coverEmployee;
    }

    private function applyShiftCover(
        string $conn,
        EmployeeScheduleShift $entry,
        string $coverAction,
        ?Employee $coverEmployee,
        ?string $createdBy,
    ): void {
        if ($coverAction === EmployeeScheduleShift::COVER_ACTION_ASSIGN_EMPLOYEE) {
            if ($coverEmployee === null) {
                throw ValidationException::withMessages([
                    'cover_employee_public_id' => 'Select an employee to cover this shift.',
                ]);
            }

            $covering = $this->syncCoveringShift($conn, $entry, $coverEmployee, $createdBy);
            $entry->cover_status = EmployeeScheduleShift::COVER_ASSIGNED;
            $entry->covering_shift_id = $covering->id;

            return;
        }

        $this->deleteCoveringShift($conn, $entry);
        $entry->covering_shift_id = null;
        $entry->cover_status = $coverAction === EmployeeScheduleShift::COVER_UNASSIGNED
            ? EmployeeScheduleShift::COVER_UNASSIGNED
            : EmployeeScheduleShift::COVER_LEAVE_UNCOVERED;
    }

    private function syncCoveringShift(
        string $conn,
        EmployeeScheduleShift $entry,
        Employee $coverEmployee,
        ?string $createdBy,
    ): EmployeeScheduleShift {
        $scheduledDate = $entry->scheduled_date instanceof CarbonInterface
            ? $entry->scheduled_date->toDateString()
            : (string) $entry->scheduled_date;

        $attributes = [
            'employee_id' => $coverEmployee->id,
            'scheduled_date' => $scheduledDate,
            'entry_type' => EmployeeScheduleShift::TYPE_SHIFT,
            'start_time' => $this->scheduleTimeHm($entry->start_time),
            'end_time' => $this->scheduleTimeHm($entry->end_time),
            'shift_id' => $entry->shift_id,
            'job_title_id' => $entry->job_title_id,
            'department_id' => $entry->department_id ?? $coverEmployee->department_id,
            'work_location_id' => $entry->work_location_id,
            'notes' => null,
            'status' => null,
            'cover_status' => null,
            'original_employee_id' => $entry->employee_id,
            'covered_from_shift_id' => $entry->id,
            'covering_shift_id' => null,
            'leave_type_id' => null,
            'leave_record_id' => null,
        ];

        if ($createdBy !== null) {
            $attributes['created_by'] = $createdBy;
        }

        $covering = null;
        if ($entry->covering_shift_id) {
            $covering = EmployeeScheduleShift::on($conn)->find($entry->covering_shift_id);
        }

        if ($covering === null) {
            $covering = EmployeeScheduleShift::on($conn)
                ->where('covered_from_shift_id', $entry->id)
                ->first();
        }

        if ($covering !== null) {
            $covering->fill($attributes)->save();

            return $covering;
        }

        /** @var EmployeeScheduleShift $covering */
        $covering = EmployeeScheduleShift::on($conn)->create($attributes);

        return $covering;
    }

    private function clearShiftCover(string $conn, EmployeeScheduleShift $entry): void
    {
        $this->deleteCoveringShift($conn, $entry);
        $entry->cover_status = null;
        $entry->covering_shift_id = null;
    }

    private function deleteCoveringShift(string $conn, EmployeeScheduleShift $entry): void
    {
        $covering = null;
        if ($entry->covering_shift_id) {
            $covering = EmployeeScheduleShift::on($conn)->find($entry->covering_shift_id);
        }

        if ($covering === null) {
            $covering = EmployeeScheduleShift::on($conn)
                ->where('covered_from_shift_id', $entry->id)
                ->first();
        }

        if ($covering !== null) {
            $covering->delete();
        }

        $entry->covering_shift_id = null;
    }

    private function detachShiftCoverOnDelete(string $conn, EmployeeScheduleShift $entry): void
    {
        if ($entry->covering_shift_id) {
            /** @var EmployeeScheduleShift|null $covering */
            $covering = EmployeeScheduleShift::on($conn)->find($entry->covering_shift_id);
            if ($covering !== null) {
                $covering->original_employee_id = null;
                $covering->covered_from_shift_id = null;
                $covering->save();
            }
        }

        if ($entry->covered_from_shift_id) {
            /** @var EmployeeScheduleShift|null $original */
            $original = EmployeeScheduleShift::on($conn)->find($entry->covered_from_shift_id);
            if ($original !== null) {
                $original->covering_shift_id = null;
                if ($original->cover_status === EmployeeScheduleShift::COVER_ASSIGNED) {
                    $original->cover_status = EmployeeScheduleShift::COVER_LEAVE_UNCOVERED;
                }
                $original->save();
            }
        }
    }

    private function scheduleTimeHm(mixed $time): ?string
    {
        if ($time instanceof CarbonInterface) {
            return $time->format('H:i');
        }

        if (is_string($time) && preg_match('/^(\d{1,2}:\d{2})/', $time, $matches) === 1) {
            return strlen($matches[1]) === 4 ? '0'.$matches[1] : $matches[1];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveScheduleShiftTemplate(array $data, string $conn): array
    {
        $shift = WorkforceShifts::findOrCreateForSchedule(
            $conn,
            (string) $data['start_time'],
            (string) $data['end_time'],
            $data['shift_days'] ?? null,
            $data['shift_breaks'] ?? null,
        );

        $data['shift_id'] = $shift->id;

        return $data;
    }

    private function dayIsOccupied(string $conn, int $employeeId, string $scheduledDate, ?int $exceptId = null): bool
    {
        $query = EmployeeScheduleShift::on($conn)
            ->where('employee_id', $employeeId)
            ->where('scheduled_date', $scheduledDate);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    private function assertDateAvailable(string $conn, int $employeeId, string $scheduledDate, ?int $exceptId = null): void
    {
        if (! $this->dayIsOccupied($conn, $employeeId, $scheduledDate, $exceptId)) {
            return;
        }

        throw ValidationException::withMessages([
            'scheduled_date' => 'This employee already has an entry on that date.',
        ]);
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

        $comment = $entry->notes !== null ? trim((string) $entry->notes) : '';
        $leaveNotes = $comment !== ''
            ? (str_starts_with(strtolower($comment), 'sick call out') ? $comment : 'Sick call out: '.$comment)
            : 'Sick call out';

        $attributes = [
            'employee_id' => $employee->id,
            'leave_type' => EmployeeLeaveRecord::TYPE_SICK,
            'is_paid' => $isPaid,
            'leave_date' => $entry->scheduled_date->toDateString(),
            'hours' => round($hours, 2),
            'hourly_rate' => $isPaid && $ordinary > 0 ? $ordinary : null,
            'notes' => $leaveNotes,
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

        if (! $start instanceof CarbonInterface || ! $end instanceof CarbonInterface) {
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
