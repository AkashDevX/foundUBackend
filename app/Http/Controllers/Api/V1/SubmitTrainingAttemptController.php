<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Support\AdminTraining;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SubmitTrainingAttemptController extends Controller
{
    public function __invoke(Request $request, int $assignment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_id' => ['required', 'integer'],
        ]);

        $row = TrainingAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->findOrFail($assignment);

        try {
            AdminTraining::submitAttempt($row, $employee, $data['answers'] ?? []);
            $row->refresh();

            return response()->json(AdminTraining::mobileDetailForAssignment(
                $row,
                $employee,
                $this->companySlug($request),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'forbidden'], 403);
        }
    }

    private function companySlug(Request $request): ?string
    {
        $company = $request->attributes->get('tenant_company');
        if ($company instanceof Company && is_string($company->slug)) {
            return $company->slug;
        }

        $header = config('tenants.identifier_header', 'X-Company-Slug');
        $slug = $request->header($header);

        return is_string($slug) && $slug !== '' ? $slug : null;
    }
}
