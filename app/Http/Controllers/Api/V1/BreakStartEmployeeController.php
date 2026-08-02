<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TimeClockException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithTimeClockErrors;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\TimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BreakStartEmployeeController extends Controller
{
    use RespondsWithTimeClockErrors;

    public function __invoke(Request $request, TimeClockService $timeClock): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        try {
            $result = $timeClock->breakStart($employee, [
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'accuracy_meters' => isset($validated['accuracy_meters'])
                    ? (float) $validated['accuracy_meters']
                    : null,
            ]);
        } catch (TimeClockException $e) {
            return $this->timeClockErrorResponse($e);
        }

        return response()->json([
            'message' => 'Break started successfully.',
            'entry' => $result['entry']->toMobilePayload(),
            'time_clock' => $result['time_clock'],
        ]);
    }
}
