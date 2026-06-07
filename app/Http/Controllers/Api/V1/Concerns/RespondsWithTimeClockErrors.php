<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Exceptions\TimeClockException;
use Illuminate\Http\JsonResponse;

trait RespondsWithTimeClockErrors
{
    protected function timeClockErrorResponse(TimeClockException $e): JsonResponse
    {
        return response()->json([
            'message' => $e->getMessage(),
            'code' => $e->errorCode,
            'details' => $e->details !== [] ? $e->details : (object) [],
        ], $e->httpStatus);
    }
}
