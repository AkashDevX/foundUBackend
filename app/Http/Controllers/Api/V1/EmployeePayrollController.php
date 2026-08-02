<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollRunLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeePayrollController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $employee->loadMissing(['assignedJobTitle']);

        $conn = $employee->getConnectionName();

        $latestRun = PayrollRun::on($conn)
            ->where('status', PayrollRun::STATUS_FINALIZED)
            ->orderByDesc('fortnight_start')
            ->first();

        $lines = [];
        if ($latestRun !== null) {
            $lines = PayrollRunLine::on($conn)
                ->where('payroll_run_id', $latestRun->id)
                ->where('employee_id', $employee->id)
                ->orderBy('sort_order')
                ->get()
                ->map(static fn (PayrollRunLine $line): array => [
                    'description' => $line->description,
                    'rate_type' => $line->rate_type,
                    'hours' => (float) $line->hours,
                    'rate' => (float) $line->rate,
                    'amount' => (float) $line->amount,
                ])
                ->values()
                ->all();
        }

        $payload = $employee->toMobileProfilePayload($request->tenantCompany());

        return response()->json([
            'employee' => $payload,
            'latest_pay_run' => $latestRun === null ? null : [
                'fortnight_start' => $latestRun->fortnight_start?->toDateString(),
                'fortnight_end' => $latestRun->fortnight_end?->toDateString(),
                'generated_at' => $latestRun->generated_at?->toIso8601String(),
                'lines' => $lines,
                'gross_total' => round(array_sum(array_column($lines, 'amount')), 2),
            ],
        ]);
    }
}
