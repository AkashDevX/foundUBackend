<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeDeviceToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'min:20', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
        ]);

        $token = trim($data['token']);

        // Same physical device token may move between employees after logout/login.
        EmployeeDeviceToken::query()->where('fcm_token', $token)->delete();

        EmployeeDeviceToken::query()->updateOrCreate(
            [
                'employee_id' => $employee->id,
                'fcm_token' => $token,
            ],
            [
                'platform' => $data['platform'],
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:512'],
        ]);

        $query = EmployeeDeviceToken::query()->where('employee_id', $employee->id);
        if (! empty($data['token'])) {
            $query->where('fcm_token', trim((string) $data['token']));
        }
        $query->delete();

        return response()->json(['ok' => true]);
    }
}
