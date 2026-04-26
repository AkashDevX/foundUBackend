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
            'employee' => $employee->toMobileProfilePayload($request->tenantCompany()),
        ]);
    }
}
