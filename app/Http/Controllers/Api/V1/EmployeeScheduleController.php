<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminWeeklySchedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeScheduleController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $week = $request->query('week');
        $weekParam = is_string($week) && $week !== '' ? $week : null;

        return response()->json([
            'schedule' => AdminWeeklySchedule::mobilePayloadForEmployee($employee, $weekParam),
        ]);
    }
}
