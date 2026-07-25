<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TimeOffRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTimeOffRequestsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $requests = TimeOffRequest::query()
            ->where('employee_id', $employee->id)
            ->orderByDesc('requested_date')
            ->orderByDesc('id')
            ->limit(60)
            ->get()
            ->map(static fn (TimeOffRequest $r): array => $r->toMobilePayload())
            ->all();

        return response()->json([
            'requests' => $requests,
        ]);
    }
}
