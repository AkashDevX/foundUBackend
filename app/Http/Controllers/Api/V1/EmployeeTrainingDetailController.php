<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Support\AdminTraining;
use App\Support\TrainingCertificates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class EmployeeTrainingDetailController extends Controller
{
    public function __invoke(Request $request, int $assignment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $row = TrainingAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->findOrFail($assignment);

        try {
            return response()->json(AdminTraining::mobileDetailForAssignment(
                $row,
                $employee,
                TrainingCertificates::organizationName($request),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'forbidden'], 403);
        }
    }

}
