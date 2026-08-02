<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\TimeClockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeClockStatusController extends Controller
{
    public function __invoke(Request $request, TimeClockService $timeClock): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        return response()->json([
            'time_clock' => $timeClock->statusFor($employee),
        ]);
    }
}
