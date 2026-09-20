<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\TimeClockException;
use App\Http\Controllers\Api\V1\Concerns\RespondsWithTimeClockErrors;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\LocationTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcknowledgeIdleAlertController extends Controller
{
    use RespondsWithTimeClockErrors;

    public function __invoke(Request $request, LocationTrackingService $tracking): JsonResponse
    {
        $validated = $request->validate([
            'idle_alert_id' => ['required', 'integer', 'min:1'],
        ]);

        /** @var Employee $employee */
        $employee = $request->user();

        try {
            $alert = $tracking->acknowledgeIdleAlert($employee, (int) $validated['idle_alert_id']);
        } catch (TimeClockException $e) {
            return $this->timeClockErrorResponse($e);
        }

        return response()->json([
            'message' => 'Idle alert acknowledged.',
            'idle_alert' => $alert->toMobilePayload(),
        ]);
    }
}
