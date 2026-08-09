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

    public static function assignmentStatus(?TrainingAttempt $attempt): string
    {
        if ($attempt === null) {
            return 'not_started';
        }
        if ($attempt->isSubmitted()) {
            return 'completed';
        }
        if ($attempt->materialsAcknowledged()) {
            return 'in_quiz';
        }

        return 'studying';
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

        foreach ($module->assignments as $assignment) {
            $status = self::assignmentStatus($assignment->attempt);
            if ($status === 'completed') {
                $completed++;
                $percent = (float) ($assignment->attempt?->percent ?? 0);
                $percentSum += $percent;
                if ($assignment->attempt?->passed === true) {
                    $passedCount++;
                }
            } elseif ($status === 'not_started') {
                $notStarted++;
            } else {
                $inProgress++;
            }
        }

        return [
            'assigned' => $assigned,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'average_percent' => $completed > 0 ? round($percentSum / $completed, 1) : null,
            'pass_rate' => $completed > 0 ? round(($passedCount / $completed) * 100, 1) : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function moduleResultRows(TrainingModule $module): array
    {
        $module->loadMissing(['assignments.attempt.answers', 'assignments.employee']);

        return $module->assignments
            ->sortBy(fn (TrainingAssignment $a) => strtolower((string) ($a->employee?->full_legal_name ?: $a->employee?->email ?: '')))
            ->values()
            ->map(function (TrainingAssignment $assignment) use ($module): array {
                $attempt = $assignment->attempt;
                $status = self::assignmentStatus($attempt);
                $percent = $attempt?->percent;

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
                    'passed' => $attempt?->passed,
                    'band' => self::scoreBand($percent !== null ? (float) $percent : null, $module->pass_percent),
                    'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
                    'materials_acknowledged_at' => $attempt?->materials_acknowledged_at?->toIso8601String(),
                    'assigned_at' => $assignment->assigned_at?->toIso8601String(),
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
            ->with(['module', 'employee', 'attempt'])
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
                $status = self::assignmentStatus($attempt);
                $percent = $attempt?->percent;
                $passPercent = $module?->pass_percent;

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

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'not_started');
            if ($status === 'completed') {
                $completed++;
                $percentSum += (float) ($row['percent'] ?? 0);
                if (($row['passed'] ?? null) === true) {
                    $passedCount++;
                }
            } elseif ($status === 'not_started') {
                $notStarted++;
            } else {
                $inProgress++;
            }
        }

        return [
            'assigned' => $assigned,
            'completed' => $completed,
            'in_progress' => $inProgress,
            'not_started' => $notStarted,
            'average_percent' => $completed > 0 ? round($percentSum / $completed, 1) : null,
            'pass_rate' => $completed > 0 ? round(($passedCount / $completed) * 100, 1) : null,
        ];
    }

    public static function ensurePublishedAssignable(TrainingModule $module): void
    {
        if (! $module->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish the module before assigning it to employees.',
            ]);
        }

        $questionCount = $module->questions()->count();
        if ($questionCount < 1) {
            throw ValidationException::withMessages([
                'questions' => 'Add at least one multiple-choice question before assigning.',
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
        }

        return [
            'question_order' => $questionIds,
            'option_order' => $optionOrder,
        ];
    }

    /**
     * @param  list<array{question_id: int, option_id: int}>  $answers
     */
    public static function submitAttempt(TrainingAssignment $assignment, Employee $employee, array $answers): TrainingAttempt
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

        if ($attempt->isSubmitted()) {
            throw ValidationException::withMessages([
                'assignment' => 'This training has already been submitted.',
            ]);
        }

        $questions = $assignment->module->questions->keyBy('id');
        $expectedIds = collect($attempt->question_order ?? $questions->keys()->all())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $answerMap = [];
        foreach ($answers as $row) {
            $qid = (int) ($row['question_id'] ?? 0);
            $oid = (int) ($row['option_id'] ?? 0);
            if ($qid > 0 && $oid > 0) {
                $answerMap[$qid] = $oid;
            }
        }

        // Timed quizzes may leave unanswered questions; those score as incorrect.
        $conn = $assignment->getConnectionName();

        return DB::connection($conn)->transaction(function () use ($attempt, $questions, $answerMap, $assignment, $expectedIds, $conn): TrainingAttempt {
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
                $selectedId = $answerMap[$qid] ?? null;
                /** @var TrainingQuestionOption|null $selected */
                $selected = $selectedId !== null
                    ? $question->options->firstWhere('id', $selectedId)
                    : null;
                $isCorrect = $selected !== null && $selected->is_correct === true;
                if ($isCorrect) {
                    $score += $points;
                }

                TrainingAttemptAnswer::on($conn)->create([
                    'training_attempt_id' => $attempt->id,
                    'training_question_id' => $qid,
                    'selected_option_id' => $selected?->id,
                    'is_correct' => $isCorrect,
                ]);
            }

            $percent = $maxScore > 0 ? round(($score / $maxScore) * 100, 2) : 0.0;
            $passPercent = $assignment->module->pass_percent;
            $passed = $passPercent === null ? null : $percent >= (int) $passPercent;

            $attempt->fill([
                'score' => $score,
                'max_score' => $maxScore,
                'percent' => $percent,
                'passed' => $passed,
                'submitted_at' => now(),
            ]);
            $attempt->save();

            return $attempt->fresh(['answers']) ?? $attempt;
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
        DB::connection($conn)->transaction(function () use ($attempt, $conn): void {
            TrainingAttemptAnswer::on($conn)->where('training_attempt_id', $attempt->id)->delete();
            $attempt->delete();
        });
    }

    /**
     * Mobile list payload for the signed-in employee.
     *
     * @return array{trainings: list<array<string, mixed>>}
     */
    public static function mobileListForEmployee(Employee $employee): array
    {
        $conn = $employee->getConnectionName();

        $assignments = TrainingAssignment::on($conn)
            ->where('employee_id', $employee->id)
            ->with(['module.pages', 'module.questions', 'attempt'])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TrainingAssignment $a) => $a->module !== null && $a->module->isPublished())
            ->values();

        return [
            'trainings' => $assignments->map(fn (TrainingAssignment $a) => self::mobileAssignmentSummary($a))->all(),
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
        ?string $companySlug = null,
    ): array {
        if ((int) $assignment->employee_id !== (int) $employee->id) {
            throw new InvalidArgumentException('Assignment does not belong to this employee.');
        }

        $assignment->loadMissing(['module.pages.sections', 'module.questions.options', 'attempt.answers']);

        $module = $assignment->module;
        if ($module === null || ! $module->isPublished()) {
            throw ValidationException::withMessages([
                'assignment' => 'This training is no longer available.',
            ]);
        }

        $attempt = $assignment->attempt;
        $revealCorrect = $attempt?->isSubmitted() === true;

        return [
            'assignment' => self::mobileAssignmentSummary($assignment),
            'pages' => $module->pages->map(static function ($page): array {
                return [
                    'id' => $page->id,
                    'title' => $page->title,
                    'body' => $page->body ?? '',
                    'sort_order' => $page->sort_order,
                    'sections' => $page->sections->map(static function ($section): array {
                        return [
                            'id' => $section->id,
                            'title' => $section->title,
                            'body' => $section->body,
                            'sort_order' => $section->sort_order,
                        ];
                    })->values()->all(),
                ];
            })->values()->all(),
            'quiz_unlocked' => $attempt?->materialsAcknowledged() === true,
            'questions' => self::orderedQuestionsForAttempt($module, $attempt, $revealCorrect),
            'result' => $revealCorrect ? self::mobileResultPayload($assignment, $attempt) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function mobileAssignmentSummary(TrainingAssignment $assignment): array
    {
        $assignment->loadMissing(['module', 'attempt']);
        $module = $assignment->module;
        $attempt = $assignment->attempt;
        $status = self::assignmentStatus($attempt);

        return [
            'id' => $assignment->id,
            'module_id' => $module?->id,
            'title' => $module?->title ?? 'Training',
            'description' => $module?->description,
            'pass_percent' => $module?->pass_percent,
            'question_time_seconds' => max(10, (int) ($module?->question_time_seconds ?? 45)),
            'status' => $status,
            'pages_count' => $module?->pages?->count() ?? $module?->pages()->count() ?? 0,
            'questions_count' => $module?->questions?->count() ?? $module?->questions()->count() ?? 0,
            'assigned_at' => $assignment->assigned_at?->toIso8601String(),
            'due_date' => $assignment->due_date?->toDateString(),
            'score' => $attempt?->score,
            'max_score' => $attempt?->max_score,
            'percent' => $attempt?->percent,
            'passed' => $attempt?->passed,
            'submitted_at' => $attempt?->submitted_at?->toIso8601String(),
            'band' => self::scoreBand(
                $attempt?->percent !== null ? (float) $attempt->percent : null,
                $module?->pass_percent
            ),
        ];
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

        if ($attempt === null || ! $attempt->materialsAcknowledged()) {
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

            $out[] = [
                'id' => $question->id,
                'question_text' => $question->question_text,
                'points' => $question->points,
                'options' => $options->map(static function (TrainingQuestionOption $opt) use ($revealCorrect): array {
                    $row = [
                        'id' => $opt->id,
                        'option_text' => $opt->option_text,
                    ];
                    if ($revealCorrect) {
                        $row['is_correct'] = $opt->is_correct;
                    }

                    return $row;
                })->values()->all(),
                'selected_option_id' => $revealCorrect ? $answer?->selected_option_id : null,
                'is_correct' => $revealCorrect ? $answer?->is_correct : null,
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

        return [
            'score' => $attempt->score,
            'max_score' => $attempt->max_score,
            'percent' => $attempt->percent,
            'passed' => $attempt->passed,
            'pass_percent' => $module?->pass_percent,
            'band' => self::scoreBand(
                $attempt->percent !== null ? (float) $attempt->percent : null,
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
