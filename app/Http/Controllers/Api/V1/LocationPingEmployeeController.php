<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TimeClockException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithTimeClockErrors;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\LocationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationPingEmployeeController extends Controller
{
    use RespondsWithTimeClockErrors;

    public function __invoke(Request $request, LocationTrackingService $tracking): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_meters' => ['nullable', 'numeric', 'min:0', 'max:10000'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        try {
            $result = $tracking->recordPing($employee, [
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
            'message' => $result['throttled']
                ? 'Location ping throttled.'
                : 'Location ping recorded.',
            'throttled' => $result['throttled'],
            'sample' => $result['sample']?->toMobilePayload(),
            'idle_alert' => $result['idle_alert']?->toMobilePayload(),
        ]);
    }
}
