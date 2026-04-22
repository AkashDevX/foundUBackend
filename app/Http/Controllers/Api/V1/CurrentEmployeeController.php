<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentEmployeeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $employee->loadMissing(['assignedDepartment', 'workLocation', 'assignedShift']);

        return response()->json([
            'employee' => [
                'public_id' => $employee->public_id,
                'email' => $employee->email,
                'full_legal_name' => $employee->full_legal_name,
                'first_name' => $employee->first_name,
                'last_name' => $employee->last_name,
                'employment_status' => $employee->employment_status,
                'company_display_name' => $employee->company_display_name,
                'phone' => $employee->phone,
                'work_assignment' => $employee->workAssignmentForApi(),
            ],
        ]);
    }
}
