<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogoutEmployeeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $token = $employee->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Signed out.',
        ]);
    }
}
