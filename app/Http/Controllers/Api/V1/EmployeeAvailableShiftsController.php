<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AvailableShiftRequest;
use App\Models\Employee;
use App\Models\EmployeeScheduleShift;
use App\Support\AdminWeeklySchedule;
use App\Support\AvailableShiftOffers;
use App\Support\DisplayTimezone;
use App\Support\InductionEligibility;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EmployeeAvailableShiftsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $today = DisplayTimezone::now()->toDateString();
        $from = AdminWeeklySchedule::resolveWeekStart(null)->toDateString();

        $shifts = EmployeeScheduleShift::query()
            ->with(['jobTitle', 'department', 'workLocation', 'employee', 'coveringShift.employee'])
            ->where('entry_type', EmployeeScheduleShift::TYPE_SHIFT)
            ->where('made_available', true)
            ->whereIn('cover_status', [
                EmployeeScheduleShift::COVER_AVAILABLE,
                EmployeeScheduleShift::COVER_ASSIGNED,
            ])
            ->whereDate('scheduled_date', '>=', $from)
            ->where('employee_id', '!=', $employee->id)
            ->orderBy('scheduled_date')
            ->orderBy('start_time')
            ->orderBy('id')
            ->get();

        $requests = AvailableShiftRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('schedule_shift_id', $shifts->pluck('id'))
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'shifts' => AvailableShiftOffers::mobileItems($shifts, $employee, $requests, $today),
        ]);
    }

    public function store(Request $request, int $scheduleShift): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:500'],
        ]);

        $note = trim((string) $validated['note']);
        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'Add a note for your manager.',
            ]);
        }

        /** @var EmployeeScheduleShift|null $entry */
        $entry = EmployeeScheduleShift::query()
            ->with('coveringShift.employee')
            ->find($scheduleShift);

        if ($entry === null || $entry->entry_type !== EmployeeScheduleShift::TYPE_SHIFT || ! $entry->made_available) {
            return response()->json([
                'message' => 'That shift is not available.',
            ], 404);
        }

        if ((int) $entry->employee_id === (int) $employee->id) {
            return response()->json([
                'message' => 'You cannot request your own shift.',
                'code' => 'own_shift',
            ], 422);
        }

        if ($entry->cover_status === EmployeeScheduleShift::COVER_ASSIGNED) {
            $assignee = $entry->coveringShift?->employee;
            $name = $assignee instanceof Employee
                ? AdminWeeklySchedule::employeeDisplayName($assignee)
                : null;

            return response()->json([
                'message' => AvailableShiftOffers::assignmentNote($name),
                'code' => 'shift_assigned',
            ], 422);
        }

        if ($entry->cover_status !== EmployeeScheduleShift::COVER_AVAILABLE) {
            return response()->json([
                'message' => 'That shift is no longer available.',
                'code' => 'shift_closed',
            ], 422);
        }

        $today = DisplayTimezone::now()->toDateString();
        $scheduledDate = $entry->scheduled_date?->toDateString() ?? '';
        if ($scheduledDate === '' || $scheduledDate < $today) {
            return response()->json([
                'message' => 'This shift has already passed.',
                'code' => 'shift_passed',
            ], 422);
        }

        if (($employee->employment_status ?? '') !== 'active') {
            return response()->json([
                'message' => 'Your account needs to be active before you can request a shift.',
                'code' => 'inactive',
            ], 422);
        }

        try {
            InductionEligibility::assertCanBeScheduled($employee);
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => collect($exception->errors())->flatten()->first() ?: 'Complete induction before requesting a shift.',
                'code' => 'induction_required',
            ], 422);
        }

        $hasTimeOff = EmployeeScheduleShift::query()
            ->where('employee_id', $employee->id)
            ->where('scheduled_date', $scheduledDate)
            ->where('entry_type', EmployeeScheduleShift::TYPE_TIME_OFF)
            ->exists();

        if ($hasTimeOff) {
            return response()->json([
                'message' => 'You already have a day off on this date.',
                'code' => 'day_off',
            ], 422);
        }

        $duplicate = AvailableShiftRequest::query()
            ->where('schedule_shift_id', $entry->id)
            ->where('employee_id', $employee->id)
            ->where('status', AvailableShiftRequest::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'You already have a pending request for this shift.',
                'code' => 'duplicate_pending_request',
            ], 422);
        }

        /** @var AvailableShiftRequest $claim */
        $claim = AvailableShiftRequest::query()->create([
            'schedule_shift_id' => $entry->id,
            'employee_id' => $employee->id,
            'note' => $note,
            'status' => AvailableShiftRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Request sent. Your manager will review it.',
            'request' => [
                'id' => $claim->id,
                'status' => $claim->status,
                'note' => $claim->note,
                'decision_note' => $claim->decision_note,
            ],
        ], 201);
    }
}
