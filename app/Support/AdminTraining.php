<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\TrainingAssignment;
use App\Models\TrainingAttempt;
use App\Models\TrainingAttemptAnswer;
use App\Models\TrainingModule;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionOption;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final class AdminTraining
{
    public static function scoreBand(?float $percent, ?int $passPercent): string
    {
        if ($percent === null) {
            return 'pending';
        }

        $pass = $passPercent ?? 70;
        if ($percent >= 85) {
            return 'strong';
        }
        if ($percent >= $pass) {
            return 'pass';
        }
        if ($percent > 0) {
            return 'weak';
        }

        return 'fail';
    }

    public static function assignmentStatus(?TrainingAttempt $attempt, bool $quizRequired = true): string
    {
        return TrainingQuiz::assignmentStatus($attempt, $quizRequired);
    }

    /**
     * @return array{assigned: int, completed: int, in_progress: int, not_started: int, average_percent: float|null, pass_rate: float|null}
     */
    public static function moduleResultsSummary(TrainingModule $module): array
    {
        $module->loadMissing(['assignments.attempt', 'assignments.employee']);

        $assigned = $module->assignments->count();
        $completed = 0;
        $inProgress = 0;
        $notStarted = 0;
        $percentSum = 0.0;
        $passedCount = 0;
        $scored = 0;

        foreach ($module->assignments as $assignment) {
            $quizRequired = (bool) ($module->quiz_required ?? true);
            $status = self::assignmentStatus($assignment->attempt, $quizRequired);
            $attempt = $assignment->attempt;
            if ($status === 'completed') {
                $completed++;
            } elseif ($status === 'not_started') {
                $notStarted++;
            } else {
                $inProgress++;
            }

            if (
                $attempt !== null
                && $attempt->isSubmitted()
                && $attempt->waived !== true
                && $attempt->percent !== null
                && $attempt->passed !== null
            ) {
                $percentSum += (float) $attempt->percent;
                $scored++;
                if ($attempt->passed === true) {
                    $passedCount++;
                }
            }
        }

        return [
            'assigned' => $assigned,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'average_percent' => $scored > 0 ? round($percentSum / $scored, 1) : null,
            'pass_rate' => $scored > 0 ? round(($passedCount / $scored) * 100, 1) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function moduleResultRows(TrainingModule $module, ?string $companyName = null): array
    {
        $module->loadMissing(['assignments.attempt.answers', 'assignments.attempts', 'assignments.employee', 'assignments.certificate']);

        return $module->assignments
            ->sortBy(fn (TrainingAssignment $a) => strtolower((string) ($a->employee?->full_legal_name ?: $a->employee?->email ?: '')))
            ->values()
            ->map(function (TrainingAssignment $assignment) use ($module, $companyName): array {
                $assignment->setRelation('module', $module);
                $attempt = $assignment->attempt;
                $quizRequired = (bool) ($module->quiz_required ?? true);
                $status = self::assignmentStatus($attempt, $quizRequired);
                $pendingReview = $attempt !== null && TrainingQuiz::needsReview($attempt);
                $percent = $pendingReview ? null : $attempt?->percent;
                $certificate = TrainingCertificates::issueIfRequired($assignment, $companyName);

                return [
                    'assignment_id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'employee_public_id' => $assignment->employee?->public_id,
                    'employee_name' => $assignment->employee?->full_legal_name ?: $assignment->employee?->email ?: 'Employee',
                    'employee_email' => $assignment->employee?->email,
                    'status' => $status,
                    'score' => $attempt?->score,
                    'max_score' => $attempt?->max_score,
                    'percent' => $percent,
                    'passed' => $pendingReview ? null : $attempt?->passed,
                    'pending_review' => $pendingReview,
                    'attempts_used' => $assignment->attempts
                        ->filter(fn (TrainingAttempt $row): bool => $row->isSubmitted() && $row->waived !== true)
                        ->count(),
                    'attempts_allocated' => ((bool) $module->is_induction || (bool) ($module->allow_retakes ?? false))
                        ? max(1, (int) ($module->max_attempts ?: 1))
                        : 1,
                    'attempts_in_progress' => $assignment->attempts
                        ->filter(fn (TrainingAttempt $row): bool => ! $row->isSubmitted() && $row->waived !== true)
                        ->count(),
                    'band' => self::scoreBand($percent !== null ? (float) $percent : null, $module->pass_percent),
                    'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                    'materials_acknowledged_at' => $attempt?->materials_acknowledged_at?->toIso8601String(),
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
                    'certificate' => $certificate !== null ? TrainingCertificates::present($certificate) : null,
                ];
            })
            ->all();
    }

    /**
     * Cross-module training completion rows for the Reports section.
     *
     * @return list<array<string, mixed>>
     */
    public static function reportRows(string $connection, ?int $moduleId = null, ?int $employeeId = null): array
    {
        $query = TrainingAssignment::on($connection)
            ->with(['module', 'employee', 'attempt.answers', 'certificate'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id');

        if ($moduleId !== null && $moduleId > 0) {
            $query->where('training_module_id', $moduleId);
        }
        if ($employeeId !== null && $employeeId > 0) {
            $query->where('employee_id', $employeeId);
        }

        return $query->get()
            ->map(function (TrainingAssignment $assignment): array {
                $module = $assignment->module;
                $attempt = $assignment->attempt;
                $quizRequired = (bool) ($module?->quiz_required ?? true);
                $status = self::assignmentStatus($attempt, $quizRequired);
                $pendingReview = $attempt !== null && TrainingQuiz::needsReview($attempt);
                $percent = $pendingReview ? null : $attempt?->percent;
                $passPercent = $module?->pass_percent;

                $certificate = TrainingCertificates::issueIfRequired($assignment);

                return [
                    'assignment_id' => $assignment->id,
                    'module_id' => $module?->id,
                    'module_title' => $module?->title ?? 'Training',
                    'pass_percent' => $passPercent,
                    'employee_id' => $assignment->employee_id,
                    'employee_name' => $assignment->employee?->full_legal_name
                        ?: $assignment->employee?->email
                        ?: 'Employee',
                    'employee_email' => $assignment->employee?->email,
                    'status' => $status,
                    'score' => $attempt?->score,
                    'max_score' => $attempt?->max_score,
                    'percent' => $percent,
                    'passed' => $attempt?->passed,
                    'band' => self::scoreBand(
                        $percent !== null ? (float) $percent : null,
                        $passPercent
                    ),
                    'assigned_at' => $assignment->assigned_at,
                    'due_date' => $assignment->due_date,
                    'materials_acknowledged_at' => $attempt?->materials_acknowledged_at,
                    'submitted_at' => $attempt?->submitted_at,
                    'certificate' => $certificate !== null ? TrainingCertificates::present($certificate) : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Aggregate summary for a list of report rows.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{assigned: int, completed: int, in_progress: int, not_started: int, average_percent: float|null, pass_rate: float|null}
     */
    public static function reportSummary(array $rows): array
    {
        $assigned = count($rows);
        $completed = 0;
        $inProgress = 0;
        $notStarted = 0;
        $percentSum = 0.0;
        $passedCount = 0;
        $scored = 0;

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'not_started');
            if ($status === 'completed') {
                $completed++;
            } elseif ($status === 'not_started') {
                $notStarted++;
            } else {
                $inProgress++;
            }
            if (($row['percent'] ?? null) !== null && ($row['passed'] ?? null) !== null) {
                $percentSum += (float) $row['percent'];
                $scored++;
                if (($row['passed'] ?? null) === true) {
                    $passedCount++;
                }
            }
        }

        return [
            'assigned' => $assigned,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'average_percent' => $scored > 0 ? round($percentSum / $scored, 1) : null,
            'pass_rate' => $scored > 0 ? round(($passedCount / $scored) * 100, 1) : null,
        ];
    }

    public static function ensurePublishedAssignable(TrainingModule $module): void
    {
        if (! $module->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish the module before assigning it to employees.',
            ]);
        }

        $quizRequired = (bool) ($module->quiz_required ?? true);
        $questionCount = $module->questions()->count();
        if ($quizRequired && $questionCount < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Add at least one quiz question before assigning, or make the quiz optional.',
            ]);
        }

        $pageCount = $module->pages()->count();
        if ($pageCount < 1) {
            throw ValidationException::withMessages([
                'pages' => 'Add at least one study page before assigning.',
            ]);
        }
    }

    /**
     * Create or reuse attempt and persist shuffled question/option order.
     */
    public static function acknowledgeMaterials(TrainingAssignment $assignment, Employee $employee): TrainingAttempt
    {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing(['module.questions.options', 'attempt']);

        if ($assignment->attempt?->isSubmitted()) {
            throw ValidationException::withMessages([
                'assignment' => 'This training has already been submitted.',
            ]);
        }

        $conn = $assignment->getConnectionName();
        $attempt = $assignment->attempt;
        if ($attempt === null) {
            $attempt = TrainingAttempt::on($conn)->create([
                'training_assignment_id' => $assignment->id,
                'employee_id' => $employee->id,
                'attempt_number' => (int) $assignment->attempts()->count() + 1,
            ]);
        }

        if (! $attempt->materialsAcknowledged()) {
            $shuffle = self::buildShuffle($assignment->module);
            $attempt->question_order = $shuffle['question_order'];
            $attempt->option_order = $shuffle['option_order'];
            $attempt->materials_acknowledged_at = now();
            $attempt->save();
        }

        return $attempt->fresh(['answers']) ?? $attempt;
    }

    /**
     * @return array{question_order: list<int>, option_order: array<string, list<int>>}
     */
    public static function buildShuffle(TrainingModule $module): array
    {
        $module->loadMissing(['questions.options']);

        $questionIds = $module->questions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        shuffle($questionIds);

        $optionOrder = [];
        foreach ($module->questions as $question) {
            $optionIds = $question->options->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            shuffle($optionIds);
            $optionOrder[(string) $question->id] = $optionIds;
            if (TrainingQuiz::typeOf($question) === 'matching') {
                $matches = $question->options
                    ->pluck('match_text')
                    ->map(fn ($text) => trim((string) $text))
                    ->filter(fn (string $text): bool => $text !== '')
                    ->unique()
                    ->values()
                    ->all();
                shuffle($matches);
                $optionOrder['matches:'.$question->id] = $matches;
            }
        }

        return [
            'question_order' => $questionIds,
            'option_order' => $optionOrder,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $answers
     * @param  array{signed: bool, width: int, height: int, strokes: list<list<array{x: float, y: float}>>}|null  $signature
     * @return array{attempt: TrainingAttempt, already_submitted: bool}
     */
    public static function submitAttempt(TrainingAssignment $assignment, Employee $employee, array $answers, ?array $signature = null, bool $abandon = false, ?string $companyName = null): array
    {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing(['module.questions.options', 'attempt']);
        $attempt = $assignment->attempt;

        if ($attempt === null || ! $attempt->materialsAcknowledged()) {
            throw ValidationException::withMessages([
                'assignment' => 'Study the pages and acknowledge them before submitting answers.',
            ]);
        }

        $questions = $assignment->module->questions->keyBy('id');
        $expectedIds = collect($attempt->question_order ?? $questions->keys()->all())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $answerMap = [];
        foreach ($answers as $row) {
            if (! is_array($row)) {
                continue;
            }
            $qid = (int) ($row['question_id'] ?? 0);
            if ($qid > 0) {
                $answerMap[$qid] = $row;
            }
        }

        // Timed quizzes may leave unanswered questions; those score as incorrect.
        $conn = $assignment->getConnectionName();

        return DB::connection($conn)->transaction(function () use ($attempt, $questions, $answerMap, $assignment, $expectedIds, $conn, $signature, $abandon, $companyName): array {
            $locked = TrainingAttempt::on($conn)->whereKey($attempt->id)->lockForUpdate()->first();
            if (! $locked instanceof TrainingAttempt || ! $locked->materialsAcknowledged()) {
                throw ValidationException::withMessages([
                    'assignment' => 'Study the pages and acknowledge them before submitting answers.',
                ]);
            }
            if ($locked->isSubmitted()) {
                $saved = $locked->fresh(['answers']) ?? $locked;
                $assignment->setRelation('attempt', $saved);
                TrainingCertificates::issueIfRequired($assignment, $companyName, $signature);

                return ['attempt' => $saved, 'already_submitted' => true];
            }

            $attempt = $locked;
            TrainingAttemptAnswer::on($conn)
                ->where('training_attempt_id', $attempt->id)
                ->delete();

            $score = 0;
            $maxScore = 0;

            foreach ($expectedIds as $qid) {
                /** @var TrainingQuestion|null $question */
                $question = $questions->get($qid);
                if ($question === null) {
                    continue;
                }

                $points = max(1, (int) $question->points);
                $maxScore += $points;
                $graded = TrainingQuiz::grade($question, $answerMap[$qid] ?? []);
                if ($graded['review_status'] !== 'pending') {
                    $score += $graded['points_awarded'];
                }

                TrainingAttemptAnswer::on($conn)->create([
                    'training_attempt_id' => $attempt->id,
                    'training_question_id' => $qid,
                    'selected_option_id' => $graded['selected_option_id'],
                    'is_correct' => $graded['is_correct'],
                    'response' => $graded['response'],
                    'points_awarded' => $graded['points_awarded'],
                    'review_status' => $graded['review_status'],
                ]);
            }

            if ($abandon) {
                TrainingAttemptAnswer::on($conn)
                    ->where('training_attempt_id', $attempt->id)
                    ->where('review_status', 'pending')
                    ->update([
                        'review_status' => null,
                        'is_correct' => false,
                        'points_awarded' => 0,
                    ]);
                $score = (int) TrainingAttemptAnswer::on($conn)
                    ->where('training_attempt_id', $attempt->id)
                    ->sum('points_awarded');
            }

            $pending = ! $abandon && TrainingAttemptAnswer::on($conn)
                ->where('training_attempt_id', $attempt->id)
                ->where('review_status', 'pending')
                ->exists();
            $percent = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0;
            $passPercent = $assignment->module->pass_percent;
            $passed = $abandon
                ? false
                : ($pending || $passPercent === null ? null : $percent >= (int) $passPercent);

            $attempt->fill([
                'score' => $score,
                'max_score' => $maxScore,
                'percent' => $percent,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);
            $attempt->save();

            $saved = $attempt->fresh(['answers']) ?? $attempt;
            $assignment->setRelation('attempt', $saved);
            TrainingCertificates::issueIfRequired($assignment, $companyName, $signature);

            return ['attempt' => $saved, 'already_submitted' => false];
        });
    }

    public static function resetAttempt(TrainingAssignment $assignment): void
    {
        $assignment->loadMissing('attempt');
        $attempt = $assignment->attempt;
        if ($attempt === null) {
            return;
        }

        $conn = $assignment->getConnectionName();
        $assignment->loadMissing('attempts');
        DB::connection($conn)->transaction(function () use ($assignment, $conn): void {
            TrainingCertificates::revoke($assignment);
            foreach ($assignment->attempts as $row) {
                TrainingAttemptAnswer::on($conn)->where('training_attempt_id', $row->id)->delete();
                $row->delete();
            }
        });
    }

    public static function beginRetake(TrainingAssignment $assignment, Employee $employee): ?TrainingAttempt
    {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing(['module.questions.options', 'attempt', 'attempts']);
        $module = $assignment->module;
        $latest = $assignment->attempt;
        if ($module === null) {
            throw ValidationException::withMessages([
                'assignment' => 'This training is no longer available.',
            ]);
        }
        if ($module->is_induction) {
            return self::reopenInductionAttempt($assignment, $latest);
        }
        if ($latest !== null && ! $latest->isSubmitted()) {
            $usedAlready = $assignment->attempts
                ->filter(fn (TrainingAttempt $row): bool => $row->isSubmitted() && $row->waived !== true)
                ->count();
            if ($usedAlready < 1) {
                throw ValidationException::withMessages([
                    'assignment' => 'Finish the current quiz before starting another attempt.',
                ]);
            }
            $latest->forceFill([
                'materials_acknowledged_at' => null,
                'question_order' => null,
                'option_order' => null,
            ])->save();

            return $latest->fresh() ?? $latest;
        }
        if ($latest === null) {
            throw ValidationException::withMessages([
                'assignment' => 'Finish the current quiz before starting another attempt.',
            ]);
        }
        if (TrainingQuiz::needsReview($latest)) {
            throw ValidationException::withMessages([
                'assignment' => 'This attempt is waiting for an administrator to mark it.',
            ]);
        }
        if ($latest->passed === true) {
            throw ValidationException::withMessages([
                'assignment' => 'This training is already complete.',
            ]);
        }
        if (! (bool) ($module->allow_retakes ?? false)) {
            throw ValidationException::withMessages([
                'assignment' => 'Retakes are not allowed for this quiz.',
            ]);
        }

        $used = $assignment->attempts
            ->filter(fn (TrainingAttempt $row): bool => $row->isSubmitted() && $row->waived !== true)
            ->count();
        $maxAttempts = max(1, (int) ($module->max_attempts ?: 1));
        if ($used >= $maxAttempts) {
            throw ValidationException::withMessages([
                'assignment' => 'No attempts remaining.',
            ]);
        }

        return TrainingAttempt::on($assignment->getConnectionName())->create([
            'training_assignment_id' => $assignment->id,
            'employee_id' => $employee->id,
            'attempt_number' => $used + 1,
        ]);
    }

    /**
     * A failed induction stays closed when the attempt limit was already used.
     * Raising the limit afterwards, or failing with tries still left, reopens study.
     */
    private static function reopenInductionAttempt(TrainingAssignment $assignment, ?TrainingAttempt $latest): ?TrainingAttempt
    {
        if ($latest !== null && TrainingQuiz::needsReview($latest)) {
            throw ValidationException::withMessages([
                'assignment' => 'This attempt is waiting for an administrator to mark it.',
            ]);
        }
        if ($latest?->passed === true) {
            throw ValidationException::withMessages([
                'assignment' => 'This training is already complete.',
            ]);
        }

        $fields = InductionEligibility::summaryFields($assignment);
        $used = (int) $fields['attempts_used'];
        $maxAttempts = max(1, (int) $fields['max_attempts']);
        if ($used >= $maxAttempts) {
            throw ValidationException::withMessages([
                'assignment' => 'No attempts remaining.',
            ]);
        }

        if ($latest !== null && $latest->isSubmitted()) {
            self::resetAttempt($assignment);
        }

        return null;
    }

    public static function finishWithoutQuiz(TrainingAssignment $assignment, Employee $employee, ?string $companyName = null): TrainingAttempt
    {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing(['module', 'attempt']);
        $module = $assignment->module;
        if ($module === null || (bool) ($module->quiz_required ?? true)) {
            throw ValidationException::withMessages([
                'assignment' => 'This quiz is required before the training can be completed.',
            ]);
        }

        $attempt = $assignment->attempt;
        if ($attempt !== null && $attempt->isSubmitted() && $attempt->waived !== true) {
            throw ValidationException::withMessages([
                'assignment' => 'This quiz has already been submitted.',
            ]);
        }

        $conn = $assignment->getConnectionName();
        if ($attempt === null) {
            $attempt = TrainingAttempt::on($conn)->create([
                'training_assignment_id' => $assignment->id,
                'employee_id' => $employee->id,
                'attempt_number' => 1,
            ]);
        }

        $attempt->fill([
            'materials_acknowledged_at' => $attempt->materials_acknowledged_at ?? now(),
            'waived' => true,
            'score' => null,
            'max_score' => null,
            'percent' => null,
            'passed' => true,
            'submitted_at' => now(),
        ]);
        $attempt->save();

        $saved = $attempt->fresh(['answers']) ?? $attempt;
        $assignment->setRelation('attempt', $saved);
        TrainingCertificates::issueIfRequired($assignment, $companyName);

        return $saved;
    }

    /**
     * @param  array<int, array{correct?: mixed, points?: mixed}>  $marks
     */
    public static function applyReview(TrainingAssignment $assignment, array $marks, string $reviewer, ?string $companyName = null): void
    {
        $assignment->loadMissing(['module', 'attempt.answers.question']);
        $attempt = $assignment->attempt;
        if ($attempt === null || ! $attempt->isSubmitted() || $attempt->waived === true) {
            throw ValidationException::withMessages([
                'review' => 'There is no submitted quiz to mark.',
            ]);
        }

        $wasPending = TrainingQuiz::needsReview($attempt);
        $conn = $assignment->getConnectionName();
        DB::connection($conn)->transaction(function () use ($attempt, $marks, $reviewer, $assignment, $companyName, $wasPending): void {
            foreach ($attempt->answers as $answer) {
                $question = $answer->question;
                if (! $question instanceof TrainingQuestion) {
                    continue;
                }
                $payload = $marks[$answer->id] ?? $marks[(string) $answer->id] ?? null;
                if (! in_array($answer->review_status, ['pending', 'approved', 'rejected'], true) || ! is_array($payload)) {
                    continue;
                }

                $correct = filter_var($payload['correct'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $maxPoints = max(1, (int) $question->points);
                $points = $correct ? $maxPoints : 0;
                if ($correct && isset($payload['points']) && is_numeric($payload['points'])) {
                    $points = max(0, min($maxPoints, (int) $payload['points']));
                }

                $answer->fill([
                    'is_correct' => $correct,
                    'points_awarded' => $points,
                    'review_status' => $correct ? 'approved' : 'rejected',
                    'reviewed_at' => now(),
                    'reviewed_by' => $reviewer,
                ]);
                $answer->save();
            }

            self::recomputeAttempt($attempt, $assignment);
            $assignment->unsetRelation('attempt');
            $assignment->setRelation('attempt', $attempt);
            if ($attempt->passed === true) {
                TrainingCertificates::issueIfRequired($assignment, $companyName);
            } else {
                TrainingCertificates::revoke($assignment);
            }

            if ($wasPending && ! TrainingQuiz::needsReview($attempt) && $assignment->module?->is_induction) {
                InductionEligibility::finalizeSubmission($assignment, $attempt->fresh() ?? $attempt);
            }
        });
    }

    private static function recomputeAttempt(TrainingAttempt $attempt, TrainingAssignment $assignment): void
    {
        $attempt->load(['answers.question']);
        $score = 0;
        $maxScore = 0;
        $pending = false;
        foreach ($attempt->answers as $answer) {
            $question = $answer->question;
            if (! $question instanceof TrainingQuestion) {
                continue;
            }
            $maxScore += max(1, (int) $question->points);
            if ($answer->review_status === 'pending') {
                $pending = true;
                continue;
            }
            $score += max(0, (int) $answer->points_awarded);
        }

        $percent = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0;
        $passPercent = $assignment->module?->pass_percent;
        $attempt->fill([
            'score' => $score,
            'max_score' => $maxScore,
            'percent' => $percent,
            'passed' => $pending || $passPercent === null ? null : $percent >= (int) $passPercent,
        ]);
        $attempt->save();
    }

    /**
     * Mobile list payload for the signed-in employee.
     *
     * @return array{trainings: list<array<string, mixed>>}
     */
    public static function mobileListForEmployee(Employee $employee, ?string $companyName = null): array
    {
        InductionEligibility::ensureAssigned($employee);
        $conn = $employee->getConnectionName();

        $assignments = TrainingAssignment::on($conn)
            ->where('employee_id', $employee->id)
            ->with(['module.pages', 'module.questions', 'attempt.answers', 'attempts', 'certificate'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TrainingAssignment $a) => $a->module !== null && ($a->module->isPublished() || $a->module->is_induction))
            ->values();

        return [
            'trainings' => $assignments->map(fn (TrainingAssignment $a) => self::mobileAssignmentSummary($a, $companyName))->all(),
        ];
    }

    /**
     * Full detail for one assignment (study pages + questions in attempt order).
     *
     * @return array<string, mixed>
     */
    public static function mobileDetailForAssignment(
        TrainingAssignment $assignment,
        Employee $employee,
        ?string $companyName = null,
    ): array {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing([
            'module.pages.blocks',
            'module.pages.sections.blocks',
            'module.questions.options',
            'attempt.answers',
        ]);

        $module = $assignment->module;
        if ($module === null || (! $module->isPublished() && ! $module->is_induction)) {
            throw ValidationException::withMessages([
                'assignment' => 'This training is no longer available.',
            ]);
        }

        $attempt = $assignment->attempt;
        $revealCorrect = $attempt?->isSubmitted() === true;

        return [
            'assignment' => self::mobileAssignmentSummary($assignment, $companyName),
            'pages' => $module->pages->map(static function ($page): array {
                $bullets = is_array($page->bullets) ? $page->bullets : [];

                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'body' => $page->body ?? '',
                    'bullets' => array_values(array_filter($bullets, static fn ($line): bool => is_string($line) && trim($line) !== '')),
                    'has_image' => is_string($page->image_path) && $page->image_path !== '',
                    'layout' => self::mobileLayout($page->content_order ?? null),
                    'blocks' => TrainingSlideBlocks::mobilePayload($page->blocks),
                    'sort_order' => $page->sort_order,
                    'sections' => $page->sections->map(static function ($section): array {
                        return [
                            'id' => $section->id,
                            'title' => $section->title,
                            'body' => $section->body,
                            'has_image' => is_string($section->image_path) && $section->image_path !== '',
                            'layout' => self::mobileLayout($section->content_order ?? null),
                            'blocks' => TrainingSlideBlocks::mobilePayload($section->blocks),
                            'sort_order' => $section->sort_order,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'quiz_unlocked' => $attempt?->materialsAcknowledged() === true,
            'questions' => self::orderedQuestionsForAttempt($module, $attempt, $revealCorrect),
            'result' => $revealCorrect ? self::mobileResultPayload($assignment, $attempt) : null,
            'induction' => InductionEligibility::detailContext($assignment),
        ];
    }

    /**
     * @return list<string>|null
     */
    private static function mobileLayout(mixed $layout): ?array
    {
        if (! is_array($layout) || $layout === []) {
            return null;
        }

        $out = [];
        foreach ($layout as $token) {
            if (is_string($token) && preg_match('/^(title|body|bullets|image|block:\d+)$/', $token) === 1) {
                $out[] = $token;
            }
        }

        return $out === [] ? null : $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileAssignmentSummary(TrainingAssignment $assignment, ?string $companyName = null): array
    {
        $assignment->loadMissing(['module', 'attempt.answers', 'attempts', 'employee', 'certificate']);
        $module = $assignment->module;
        $attempt = $assignment->attempt;
        $quizRequired = (bool) ($module?->quiz_required ?? true);
        $pendingReview = $attempt !== null && TrainingQuiz::needsReview($attempt);
        $status = self::assignmentStatus($attempt, $quizRequired);
        $certificate = TrainingCertificates::issueIfRequired($assignment, $companyName);
        $percent = $pendingReview ? null : $attempt?->percent;
        $summary = [
            'id' => $assignment->id,
            'module_id' => $module?->id,
            'title' => $module?->title ?? 'Training',
            'description' => $module?->description,
            'pass_percent' => $module?->pass_percent,
            'question_time_seconds' => max(10, (int) ($module?->question_time_seconds ?? 45)),
            'issues_certificate' => true,
            'quiz_required' => $quizRequired,
            'allow_retakes' => (bool) ($module?->allow_retakes ?? false),
            'quiz_waived' => $attempt?->waived === true,
            'status' => $status,
            'pages_count' => $module?->pages?->count() ?? $module?->pages()->count() ?? 0,
            'questions_count' => $module?->questions?->count() ?? $module?->questions()->count() ?? 0,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'due_date' => $assignment->due_date?->toDateString(),
            'score' => $pendingReview ? null : $attempt?->score,
            'max_score' => $attempt?->max_score,
            'percent' => $percent,
            'passed' => $pendingReview ? null : $attempt?->passed,
            'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
            'band' => self::scoreBand(
                $percent !== null ? (float) $percent : null,
                $module?->pass_percent
            ),
            'certificate' => $certificate !== null ? TrainingCertificates::present($certificate) : null,
            ...InductionEligibility::summaryFields($assignment),
        ];

        if (! (bool) ($module?->is_induction ?? false)) {
            $used = $assignment->attempts
                ->filter(fn (TrainingAttempt $row): bool => $row->isSubmitted() && $row->waived !== true)
                ->count();
            $maxAttempts = max(1, (int) ($module?->max_attempts ?: 1));
            $allowRetakes = (bool) ($module?->allow_retakes ?? false);
            $summary['max_attempts'] = $allowRetakes ? $maxAttempts : 1;
            $summary['attempts_used'] = $used;
            $summary['attempts_remaining'] = $allowRetakes ? max(0, $maxAttempts - $used) : null;
            $summary['can_retry'] = $allowRetakes
                && $attempt?->isSubmitted() === true
                && $attempt->waived !== true
                && $attempt->passed !== true
                && ! $pendingReview
                && $used < $maxAttempts;
        } else {
            $summary['can_retry'] = false;
        }

        return $summary;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function orderedQuestionsForAttempt(
        TrainingModule $module,
        ?TrainingAttempt $attempt,
        bool $revealCorrect,
    ): array {
        $module->loadMissing(['questions.options']);

        if ($attempt === null || ! $attempt->materialsAcknowledged() || $attempt->waived === true) {
            return [];
        }

        $byId = $module->questions->keyBy('id');
        $order = $attempt->question_order;
        if (! is_array($order) || $order === []) {
            $order = $module->questions->pluck('id')->all();
        }

        $optionOrderMap = is_array($attempt->option_order) ? $attempt->option_order : [];
        $answerByQuestion = $attempt->relationLoaded('answers')
            ? $attempt->answers->keyBy('training_question_id')
            : collect();

        $out = [];
        foreach ($order as $qid) {
            /** @var TrainingQuestion|null $question */
            $question = $byId->get((int) $qid);
            if ($question === null) {
                continue;
            }

            $options = $question->options;
            $optOrder = $optionOrderMap[(string) $question->id] ?? $optionOrderMap[$question->id] ?? null;
            if (is_array($optOrder) && $optOrder !== []) {
                $optById = $options->keyBy('id');
                $sorted = collect($optOrder)
                    ->map(fn ($oid) => $optById->get((int) $oid))
                    ->filter()
                    ->values();
                foreach ($options as $opt) {
                    if (! $sorted->contains(fn ($o) => (int) $o->id === (int) $opt->id)) {
                        $sorted->push($opt);
                    }
                }
                $options = $sorted;
            }

            /** @var TrainingAttemptAnswer|null $answer */
            $answer = $answerByQuestion->get($question->id);
            $response = is_array($answer?->response) ? $answer->response : [];
            $type = TrainingQuiz::typeOf($question);
            $matchChoices = $optionOrderMap['matches:'.$question->id] ?? [];
            if (! is_array($matchChoices)) {
                $matchChoices = [];
            }

            $out[] = [
                'id' => $question->id,
                'question_type' => $type,
                'question_text' => $question->question_text,
                'prompt' => $question->prompt,
                'points' => $question->points,
                'allow_multiple' => TrainingQuiz::allowsMultiple($question),
                'requires_review' => (bool) $question->requires_review,
                'has_media' => is_string($question->media_path) && $question->media_path !== '',
                'media_kind' => $question->media_kind === 'video' ? 'video' : ($question->media_kind === 'image' ? 'image' : null),
                'media_version' => TrainingSlideMedia::version($question->media_path),
                'explanation' => $revealCorrect ? $question->explanation : null,
                'options' => $options->map(static function (TrainingQuestionOption $opt) use ($revealCorrect): array {
                    $row = [
                        'id' => $opt->id,
                        'option_text' => $opt->option_text,
                    ];
                    if ($revealCorrect) {
                        $row['is_correct'] = $opt->is_correct;
                        if (is_string($opt->match_text) && $opt->match_text !== '') {
                            $row['match_text'] = $opt->match_text;
                        }
                    }

                    return $row;
                })->values()->all(),
                'match_choices' => array_values(array_filter($matchChoices, static fn ($text): bool => is_string($text) && $text !== '')),
                'correct_option_ids' => $revealCorrect && $type === 'ordering'
                    ? $question->options
                        ->sortBy(fn (TrainingQuestionOption $opt): string => sprintf('%08d-%08d', (int) $opt->sort_order, (int) $opt->id))
                        ->map(fn (TrainingQuestionOption $opt): int => (int) $opt->id)
                        ->values()
                        ->all()
                    : [],
                'selected_option_id' => $revealCorrect ? $answer?->selected_option_id : null,
                'selected_option_ids' => $revealCorrect ? array_values(array_map('intval', (array) ($response['option_ids'] ?? []))) : [],
                'response_text' => $revealCorrect ? (isset($response['text']) ? (string) $response['text'] : null) : null,
                'response_order' => $revealCorrect ? array_values(array_map('intval', (array) ($response['order'] ?? []))) : [],
                'response_matches' => $revealCorrect ? array_values(array_filter((array) ($response['matches'] ?? []), 'is_array')) : [],
                'is_correct' => $revealCorrect ? ($answer?->review_status === 'pending' ? null : $answer?->is_correct) : null,
                'review_status' => $revealCorrect ? $answer?->review_status : null,
                'points_awarded' => $revealCorrect ? $answer?->points_awarded : null,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileResultPayload(TrainingAssignment $assignment, TrainingAttempt $attempt): array
    {
        $module = $assignment->module;

        $pending = TrainingQuiz::needsReview($attempt);

        return [
            'score' => $pending ? null : $attempt->score,
            'max_score' => $attempt->max_score,
            'percent' => $pending ? null : $attempt->percent,
            'passed' => $pending ? null : $attempt->passed,
            'pass_percent' => $module?->pass_percent,
            'quiz_waived' => $attempt->waived === true,
            'pending_review' => $pending,
            'band' => self::scoreBand(
                $pending || $attempt->percent === null ? null : (float) $attempt->percent,
                $module?->pass_percent
            ),
            'submitted_at' => $attempt->submitted_at?->toIso8601String(),
        ];
    }

    /**
     * Active employees eligible for assignment multi-select.
     *
     * @return Collection<int, Employee>
     */
    public static function activeEmployees(string $connection): Collection
    {
        return Employee::on($connection)
            ->where('employment_status', 'active')
            ->orderBy('full_legal_name')
            ->orderBy('email')
            ->get();
    }
}
