<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeOffRequest;
use App\Support\DisplayTimezone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestTimeOffController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $today = DisplayTimezone::now()->toDateString();

        $validated = $request->validate([
            'requested_date' => ['required', 'date', 'after_or_equal:'.$today],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $date = $validated['requested_date'];
        $reason = isset($validated['reason']) && trim((string) $validated['reason']) !== ''
            ? trim((string) $validated['reason'])
            : null;

        // Block a second pending request for the same day.
        $duplicate = TimeOffRequest::query()
            ->where('employee_id', $employee->id)
            ->whereDate('requested_date', $date)
            ->where('status', TimeOffRequest::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            return response()->json([
                'message' => 'You already have a pending time-off request for that date.',
                'code' => 'duplicate_pending_request',
            ], 422);
        }

        /** @var TimeOffRequest $timeOff */
        $timeOff = TimeOffRequest::query()->create([
            'employee_id' => $employee->id,
            'requested_date' => $date,
            'reason' => $reason,
            'status' => TimeOffRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Time-off request submitted. Your manager will review it.',
            'request' => $timeOff->toMobilePayload(),
        ], 201);
    }
}
