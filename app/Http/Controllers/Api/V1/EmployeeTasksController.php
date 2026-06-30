<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminEmployeeTasks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTasksController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $date = $request->query('date');
        $dateParam = is_string($date) && $date !== '' ? $date : null;

        return response()->json(AdminEmployeeTasks::mobilePayloadForEmployee($employee, $dateParam));
    }
}
