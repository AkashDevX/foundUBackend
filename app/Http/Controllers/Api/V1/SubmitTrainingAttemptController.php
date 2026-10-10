<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Support\AdminTraining;
use App\Support\InductionEligibility;
use App\Support\TrainingCertificates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SubmitTrainingAttemptController extends Controller
{
    public function __invoke(Request $request, int $assignment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();

        foreach (['signature_width', 'signature_height'] as $field) {
            $value = $request->input($field);
            if (is_numeric($value)) {
                $request->merge([$field => (int) round((float) $value)]);
            }
        }

        $data = $request->validate([
            'answers' => ['nullable', 'array'],
            'answers.*.question_id' => ['required', 'integer'],
            'answers.*.option_id' => ['nullable', 'integer'],
            'answers.*.option_ids' => ['nullable', 'array'],
            'answers.*.option_ids.*' => ['integer'],
            'answers.*.text' => ['nullable', 'string', 'max:5000'],
            'answers.*.order' => ['nullable', 'array'],
            'answers.*.order.*' => ['integer'],
            'answers.*.matches' => ['nullable', 'array'],
            'answers.*.matches.*.option_id' => ['required', 'integer'],
            'answers.*.matches.*.match_text' => ['nullable', 'string', 'max:500'],
            'signature_width' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'signature_height' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'signature_strokes' => ['nullable', 'array', 'max:80'],
            'signature_strokes.*' => ['array', 'max:500'],
            'signature_strokes.*.*' => ['array'],
            'signature_strokes.*.*.x' => ['numeric'],
            'signature_strokes.*.*.y' => ['numeric'],
            'abandoned' => ['nullable', 'boolean'],
        ]);

        $row = TrainingAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->with('module')
            ->findOrFail($assignment);

        $signature = TrainingCertificates::normalizeSignature([
            'width' => $data['signature_width'] ?? null,
            'height' => $data['signature_height'] ?? null,
            'strokes' => $data['signature_strokes'] ?? [],
        ]);
        $abandoned = $request->boolean('abandoned');
        if (! $signature['signed'] && ! $abandoned) {
            throw ValidationException::withMessages([
                'signature' => 'Draw your signature before submitting this training.',
            ]);
        }

        try {
            $companyName = TrainingCertificates::organizationName($request);
            $submission = AdminTraining::submitAttempt(
                $row,
                $employee,
                $data['answers'] ?? [],
                $signature['signed'] ? $signature : null,
                $abandoned,
                $companyName,
            );
            $attempt = $submission['attempt'];
            $row->unsetRelation('attempt');
            $row->setRelation('attempt', $attempt);
            $detail = AdminTraining::mobileDetailForAssignment(
                $row,
                $employee,
                $companyName,
            );
            if (! $submission['already_submitted']) {
                $detail['induction'] = InductionEligibility::finalizeSubmission($row, $attempt);
            }

            return response()->json($detail);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'forbidden'], 403);
        }
    }

    public function retake(Request $request, int $assignment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $row = TrainingAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->findOrFail($assignment);

        try {
            AdminTraining::beginRetake($row, $employee);
            $row->unsetRelation('attempt');
            $row->unsetRelation('attempts');

            return response()->json(AdminTraining::mobileDetailForAssignment(
                $row->fresh() ?? $row,
                $employee,
                TrainingCertificates::organizationName($request),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'forbidden'], 403);
        }
    }

    public function finish(Request $request, int $assignment): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user();
        $row = TrainingAssignment::on($employee->getConnectionName())
            ->where('employee_id', $employee->id)
            ->findOrFail($assignment);

        try {
            AdminTraining::finishWithoutQuiz($row, $employee, TrainingCertificates::organizationName($request));
            $row->unsetRelation('attempt');

            return response()->json(AdminTraining::mobileDetailForAssignment(
                $row->fresh() ?? $row,
                $employee,
                TrainingCertificates::organizationName($request),
            ));
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'forbidden'], 403);
        }
    }

}
