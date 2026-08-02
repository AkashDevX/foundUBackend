<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\TimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CurrentEmployeeController extends Controller
{
    public function __invoke(Request $request, TimeClockService $timeClock): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $employee->loadMissing(['assignedDepartment', 'assignedJobTitle', 'workLocation', 'assignedShift']);

        $payload = $employee->toMobileProfilePayload($request->tenantCompany());

        try {
            $payload['time_clock'] = $timeClock->statusFor($employee);
        } catch (Throwable $e) {
            Log::warning('time_clock status skipped for /me', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'employee' => $payload,
        ]);
    }
}
