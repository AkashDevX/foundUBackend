<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TimeClockException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithTimeClockErrors;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\LocationTrackingService;
use App\Services\TimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationPingEmployeeController extends Controller
{
    use RespondsWithTimeClockErrors;

    public function __invoke(Request $request, LocationTrackingService $tracking, TimeClockService $timeClock): JsonResponse
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

        $autoClockedOut = null;
        try {
            $autoClockedOut = $timeClock->autoClockOutOnGeofenceExit($employee, [
                'latitude' => (float) $validated['latitude'],
                'longitude' => (float) $validated['longitude'],
                'accuracy_meters' => isset($validated['accuracy_meters'])
                    ? (float) $validated['accuracy_meters']
                    : null,
            ]);
        } catch (TimeClockException $e) {
            if (! in_array($e->errorCode, ['still_within_geofence', 'not_clocked_in', 'work_location_not_found'], true)) {
                return $this->timeClockErrorResponse($e);
            }
        }

        $clockedOut = $autoClockedOut !== null;

        return response()->json([
            'message' => $clockedOut
                ? 'Automatically clocked out because you left your assigned work site.'
                : ($result['throttled']
                    ? 'Location ping throttled.'
                    : 'Location ping recorded.'),
            'throttled' => $result['throttled'],
            'auto_clocked_out' => $clockedOut,
            'sample' => $result['sample']?->toMobilePayload(),
            'idle_alert' => $clockedOut ? null : $result['idle_alert']?->toMobilePayload(),
            'time_clock' => $clockedOut ? $autoClockedOut['time_clock'] : null,
        ]);
    }
}
