<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeTrainingListController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json(AdminTraining::mobileListForEmployee($employee));
    }
}
