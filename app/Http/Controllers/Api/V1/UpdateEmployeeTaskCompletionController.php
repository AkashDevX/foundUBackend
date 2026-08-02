<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\AdminEmployeeTasks;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class UpdateEmployeeTaskCompletionController extends Controller
{
    public function __invoke(Request $request, int $task): JsonResponse
    {
        $validated = $request->validate([
            'completed' => ['sometimes', 'boolean'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $completed = array_key_exists('completed', $validated)
            ? (bool) $validated['completed']
            : (bool) $request->route('completed', true);

        /** @var Employee $employee */
        $employee = $request->user();
        $date = AdminEmployeeTasks::resolveDate(
            isset($validated['date']) && is_string($validated['date']) ? $validated['date'] : null,
        );

        try {
            $taskPayload = AdminEmployeeTasks::setTaskCompletion(
                $employee,
                $task,
                $date,
                $completed,
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }

        return response()->json([
            'task' => $taskPayload,
        ]);
    }
}
