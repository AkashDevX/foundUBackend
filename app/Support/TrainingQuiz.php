<?php

namespace App\Support;

use App\Models\TrainingAttempt;
use App\Models\TrainingAttemptAnswer;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionOption;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Knowledge-check question types, scoring, and the training status those scores produce.
 */
final class TrainingQuiz
{
    /** @var array<string, string> */
    public const TYPE_LABELS = [
        'multiple_choice' => 'Multiple choice',
        'single_choice' => 'Single choice',
        'multiple_answer' => 'Multiple answer',
        'true_false' => 'True / false',
        'yes_no' => 'Yes / no',
        'short_answer' => 'Short answer',
        'scenario' => 'Scenario-based',
        'matching' => 'Matching',
        'ordering' => 'Ordering',
        'fill_blank' => 'Fill in the blank',
        'image' => 'Image-based',
        'video' => 'Video-based',
    ];

    public static function typeOf(TrainingQuestion $question): string
    {
        $type = (string) ($question->question_type ?: 'multiple_choice');

        return array_key_exists($type, self::TYPE_LABELS) ? $type : 'multiple_choice';
    }

    public static function label(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? 'Multiple choice';
    }

    public static function allowsMultiple(TrainingQuestion $question): bool
    {
        if (self::typeOf($question) === 'multiple_answer') {
            return true;
        }

        return $question->options->where('is_correct', true)->count() > 1;
    }

    public static function needsReview(TrainingAttempt $attempt): bool
    {
        $attempt->loadMissing('answers');

        return $attempt->answers->contains(
            fn (TrainingAttemptAnswer $answer): bool => $answer->review_status === 'pending'
        );
    }

    /**
     * Training is completed only when every required step is satisfied.
     * A submitted quiz that did not pass is failed when the quiz is mandatory.
     */
    public static function assignmentStatus(?TrainingAttempt $attempt, bool $quizRequired = true): string
    {
        if ($attempt === null) {
            return 'not_started';
        }
        if (! $attempt->isSubmitted()) {
            return $attempt->materialsAcknowledged() ? 'in_quiz' : 'studying';
        }
        if (self::needsReview($attempt)) {
            return 'pending_review';
        }
        if ($attempt->waived === true || $attempt->passed === true) {
            return 'completed';
        }
        if ($attempt->passed === false && ! $quizRequired) {
            return 'completed';
        }
        if ($attempt->passed === false) {
            return 'failed';
        }

        return 'pending_review';
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{
     *     is_correct: bool,
     *     points_awarded: int,
     *     review_status: string|null,
     *     selected_option_id: int|null,
     *     response: array<string, mixed>
     * }
     */
    public static function grade(TrainingQuestion $question, array $answer): array
    {
        $question->loadMissing('options');
        $type = self::typeOf($question);
        $points = max(1, (int) $question->points);
        $response = self::cleanResponse($answer);

        if (self::isBlank($type, $question, $response)) {
            return self::scored(false, 0, null, $response);
        }

        $correct = match ($type) {
            'multiple_answer' => self::choiceSetMatches($question, $response['option_ids']),
            'matching' => self::matchesCorrect($question, $response['matches']),
            'ordering' => self::orderMatches($question, $response['order']),
            'short_answer', 'fill_blank' => self::textMatches($question, $response['text']),
            'scenario' => $question->options->isEmpty()
                ? self::textMatches($question, $response['text'])
                : (self::allowsMultiple($question)
                    ? self::choiceSetMatches($question, $response['option_ids'] !== [] ? $response['option_ids'] : self::singleAsList($response))
                    : self::choiceOneMatches($question, $response['selected_option_id'])),
            'image', 'video' => self::allowsMultiple($question)
                ? self::choiceSetMatches($question, $response['option_ids'] !== [] ? $response['option_ids'] : self::singleAsList($response))
                : self::choiceOneMatches($question, $response['selected_option_id']),
            default => self::choiceOneMatches($question, $response['selected_option_id']),
        };

        $manual = self::holdsForReview($question, $type, $correct);
        if ($manual) {
            return self::scored(false, 0, 'pending', $response);
        }

        return self::scored($correct, $correct ? $points : 0, null, $response);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array{is_correct: bool, points_awarded: int, review_status: string|null, selected_option_id: int|null, response: array<string, mixed>}
     */
    private static function scored(bool $correct, int $points, ?string $review, array $response): array
    {
        return [
            'is_correct' => $correct,
            'points_awarded' => $points,
            'review_status' => $review,
            'selected_option_id' => $response['selected_option_id'],
            'response' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $answer
     * @return array{selected_option_id: int|null, option_ids: list<int>, text: string, order: list<int>, matches: list<array{option_id: int, match_text: string}>}
     */
    public static function cleanResponse(array $answer): array
    {
        $optionIds = [];
        foreach ((array) ($answer['option_ids'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $optionIds, true)) {
                $optionIds[] = $id;
            }
        }
        $selected = (int) ($answer['option_id'] ?? 0);
        if ($selected <= 0) {
            $selected = 0;
        }

        $order = [];
        foreach ((array) ($answer['order'] ?? []) as $id) {
            $id = (int) $id;
            if ($id > 0 && ! in_array($id, $order, true)) {
                $order[] = $id;
            }
        }

        $matches = [];
        foreach ((array) ($answer['matches'] ?? []) as $pair) {
            if (! is_array($pair)) {
                continue;
            }
            $id = (int) ($pair['option_id'] ?? 0);
            $text = self::normalize((string) ($pair['match_text'] ?? ''));
            if ($id > 0 && $text !== '') {
                $matches[] = ['option_id' => $id, 'match_text' => trim((string) $pair['match_text'])];
            }
        }

        return [
            'selected_option_id' => $selected > 0 ? $selected : null,
            'option_ids' => $optionIds,
            'text' => trim((string) ($answer['text'] ?? '')),
            'order' => $order,
            'matches' => $matches,
        ];
    }

    /**
     * @param  array{selected_option_id: int|null, option_ids: list<int>, text: string, order: list<int>, matches: list<array{option_id: int, match_text: string}>}  $response
     */
    private static function isBlank(string $type, TrainingQuestion $question, array $response): bool
    {
        return match ($type) {
            'multiple_answer' => $response['option_ids'] === [],
            'matching' => $response['matches'] === [],
            'ordering' => $response['order'] === [],
            'short_answer', 'fill_blank' => $response['text'] === '',
            'scenario' => $question->options->isEmpty()
                ? $response['text'] === ''
                : ($response['selected_option_id'] === null && $response['option_ids'] === []),
            'image', 'video' => self::allowsMultiple($question)
                ? ($response['option_ids'] === [] && $response['selected_option_id'] === null)
                : $response['selected_option_id'] === null,
            default => $response['selected_option_id'] === null,
        };
    }

    private static function holdsForReview(TrainingQuestion $question, string $type, bool $correct): bool
    {
        if ($correct) {
            return false;
        }

        $written = $type === 'short_answer' || $type === 'fill_blank' || ($type === 'scenario' && $question->options->isEmpty());

        return $written && $question->requires_review === true;
    }

    private static function choiceOneMatches(TrainingQuestion $question, ?int $selectedId): bool
    {
        if ($selectedId === null) {
            return false;
        }
        $selected = $question->options->firstWhere('id', $selectedId);

        return $selected instanceof TrainingQuestionOption && $selected->is_correct === true;
    }

    /**
     * @param  list<int>  $selectedIds
     */
    private static function choiceSetMatches(TrainingQuestion $question, array $selectedIds): bool
    {
        $correct = $question->options
            ->filter(fn (TrainingQuestionOption $option): bool => $option->is_correct === true)
            ->map(fn (TrainingQuestionOption $option): int => (int) $option->id)
            ->sort()
            ->values()
            ->all();
        $given = collect($selectedIds)->map(fn ($id) => (int) $id)->unique()->sort()->values()->all();

        return $correct !== [] && $given === $correct;
    }

    /**
     * @param  array{selected_option_id: int|null, option_ids: list<int>}  $response
     * @return list<int>
     */
    private static function singleAsList(array $response): array
    {
        return $response['selected_option_id'] !== null ? [$response['selected_option_id']] : [];
    }

    private static function textMatches(TrainingQuestion $question, string $text): bool
    {
        $accepted = self::acceptedAnswers($question);
        if ($accepted === [] || $text === '') {
            return false;
        }
        $given = self::normalize($text);

        foreach ($accepted as $answer) {
            if (self::normalize($answer) === $given) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array{option_id: int, match_text: string}>  $pairs
     */
    private static function matchesCorrect(TrainingQuestion $question, array $pairs): bool
    {
        $expected = [];
        foreach ($question->options as $option) {
            $right = self::normalize((string) ($option->match_text ?? ''));
            if ($right === '') {
                continue;
            }
            $expected[(int) $option->id] = $right;
        }
        if ($expected === []) {
            return false;
        }

        $given = [];
        foreach ($pairs as $pair) {
            $given[(int) $pair['option_id']] = self::normalize($pair['match_text']);
        }

        foreach ($expected as $id => $right) {
            if (($given[$id] ?? '') !== $right) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<int>  $order
     */
    private static function orderMatches(TrainingQuestion $question, array $order): bool
    {
        $correct = $question->options
            ->sortBy(fn (TrainingQuestionOption $option): array => [(int) $option->sort_order, (int) $option->id])
            ->map(fn (TrainingQuestionOption $option): int => (int) $option->id)
            ->values()
            ->all();

        return $correct !== [] && array_map('intval', $order) === $correct;
    }

    /**
     * @return list<string>
     */
    public static function acceptedAnswers(TrainingQuestion $question): array
    {
        $raw = $question->accepted_answers;
        if (! is_array($raw)) {
            return [];
        }

        $out = [];
        foreach ($raw as $line) {
            if (! is_string($line)) {
                continue;
            }
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line;
            }
        }

        return $out;
    }

    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return $value;
    }

    public static function preview(TrainingQuestion $question): string
    {
        $question->loadMissing('options');
        $type = self::typeOf($question);

        return match ($type) {
            'matching' => $question->options
                ->map(fn (TrainingQuestionOption $option): string => trim($option->option_text).' → '.trim((string) $option->match_text))
                ->implode(' · '),
            'ordering' => $question->options
                ->values()
                ->map(fn (TrainingQuestionOption $option, int $index): string => ($index + 1).'. '.trim($option->option_text))
                ->implode(' '),
            'short_answer', 'fill_blank' => self::acceptedAnswers($question) === []
                ? 'Marked by an administrator'
                : 'Accepted: '.implode(', ', self::acceptedAnswers($question)),
            'scenario' => $question->options->isEmpty()
                ? 'Written response'
                : self::correctOptionText($question),
            default => self::correctOptionText($question),
        };
    }

    private static function correctOptionText(TrainingQuestion $question): string
    {
        return $question->options
            ->filter(fn (TrainingQuestionOption $option): bool => $option->is_correct === true)
            ->map(fn (TrainingQuestionOption $option): string => trim($option->option_text))
            ->implode(', ');
    }

    public static function givenText(TrainingQuestion $question, ?TrainingAttemptAnswer $answer): string
    {
        if ($answer === null) {
            return 'No answer';
        }
        $response = is_array($answer->response) ? $answer->response : [];
        $type = self::typeOf($question);
        $question->loadMissing('options');

        if (in_array($type, ['short_answer', 'fill_blank'], true) || ($type === 'scenario' && $question->options->isEmpty())) {
            $text = trim((string) ($response['text'] ?? ''));

            return $text !== '' ? $text : 'No answer';
        }

        if ($type === 'ordering') {
            $ids = array_map('intval', (array) ($response['order'] ?? []));
            $byId = $question->options->keyBy('id');
            $lines = [];
            foreach ($ids as $index => $id) {
                $option = $byId->get($id);
                if ($option instanceof TrainingQuestionOption) {
                    $lines[] = ($index + 1).'. '.$option->option_text;
                }
            }

            return $lines === [] ? 'No answer' : implode(' ', $lines);
        }

        if ($type === 'matching') {
            $byId = $question->options->keyBy('id');
            $lines = [];
            foreach ((array) ($response['matches'] ?? []) as $pair) {
                if (! is_array($pair)) {
                    continue;
                }
                $option = $byId->get((int) ($pair['option_id'] ?? 0));
                if ($option instanceof TrainingQuestionOption) {
                    $lines[] = $option->option_text.' → '.trim((string) ($pair['match_text'] ?? ''));
                }
            }

            return $lines === [] ? 'No answer' : implode(' · ', $lines);
        }

        $ids = array_map('intval', (array) ($response['option_ids'] ?? []));
        if ($ids === [] && $answer->selected_option_id) {
            $ids = [(int) $answer->selected_option_id];
        }
        $byId = $question->options->keyBy('id');
        $labels = [];
        foreach ($ids as $id) {
            $option = $byId->get($id);
            if ($option instanceof TrainingQuestionOption) {
                $labels[] = $option->option_text;
            }
        }

        return $labels === [] ? 'No answer' : implode(', ', $labels);
    }

    /**
     * @return array{
     *     question_type: string,
     *     question_text: string,
     *     prompt: string|null,
     *     points: int,
     *     explanation: string|null,
     *     requires_review: bool,
     *     accepted_answers: list<string>,
     *     options: list<array{text: string, is_correct: bool, match_text: string|null}>,
     *     media_kind: string|null,
     *     media_file: UploadedFile|null
     * }
     */
    public static function normalizeAdmin(Request $request, ?TrainingQuestion $existing = null): array
    {
        $type = (string) $request->input('question_type', 'multiple_choice');
        if (! array_key_exists($type, self::TYPE_LABELS)) {
            throw ValidationException::withMessages([
                'question_type' => 'Choose a question type.',
            ]);
        }

        $data = $request->validate([
            'question_text' => ['required', 'string', 'max:2000'],
            'prompt' => ['nullable', 'string', 'max:5000'],
            'points' => ['nullable', 'integer', 'min:1', 'max:100'],
            'explanation' => ['nullable', 'string', 'max:5000'],
            'accepted_answers' => ['nullable', 'string', 'max:5000'],
            'requires_review' => ['nullable', 'boolean'],
            'scenario_mode' => ['nullable', Rule::in(['choices', 'written'])],
            'selection' => ['nullable', Rule::in(['single', 'multiple'])],
            'correct_value' => ['nullable', 'string', 'max:20'],
            'correct_index' => ['nullable', 'integer', 'min:0'],
            'correct_indexes' => ['nullable', 'array'],
            'correct_indexes.*' => ['integer', 'min:0'],
            'options' => ['nullable', 'array', 'max:12'],
            'options.*.text' => ['nullable', 'string', 'max:500'],
            'pairs' => ['nullable', 'array', 'max:12'],
            'pairs.*.left' => ['nullable', 'string', 'max:500'],
            'pairs.*.right' => ['nullable', 'string', 'max:500'],
            'items' => ['nullable', 'array', 'max:12'],
            'items.*' => ['nullable', 'string', 'max:500'],
            'media' => ['nullable', 'file'],
        ]);

        $prompt = trim((string) ($data['prompt'] ?? ''));
        $explanation = trim((string) ($data['explanation'] ?? ''));
        $accepted = self::lines($data['accepted_answers'] ?? null);
        $requiresReview = $request->boolean('requires_review');
        $options = [];
        $mediaKind = null;
        $mediaFile = $request->file('media');

        if ($type === 'true_false' || $type === 'yes_no') {
            $correct = strtolower(trim((string) ($data['correct_value'] ?? '')));
            $pair = $type === 'true_false' ? ['true' => 'True', 'false' => 'False'] : ['yes' => 'Yes', 'no' => 'No'];
            if (! array_key_exists($correct, $pair)) {
                throw ValidationException::withMessages([
                    'correct_value' => 'Mark the correct answer.',
                ]);
            }
            foreach ($pair as $key => $label) {
                $options[] = ['text' => $label, 'is_correct' => $key === $correct, 'match_text' => null];
            }
        } elseif ($type === 'matching') {
            foreach ((array) ($data['pairs'] ?? []) as $pair) {
                if (! is_array($pair)) {
                    continue;
                }
                $left = trim((string) ($pair['left'] ?? ''));
                $right = trim((string) ($pair['right'] ?? ''));
                if ($left === '' && $right === '') {
                    continue;
                }
                if ($left === '' || $right === '') {
                    throw ValidationException::withMessages([
                        'pairs' => 'Each matching pair needs both sides.',
                    ]);
                }
                $options[] = ['text' => $left, 'is_correct' => false, 'match_text' => $right];
            }
            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'pairs' => 'Add at least two pairs to match.',
                ]);
            }
        } elseif ($type === 'ordering') {
            foreach ((array) ($data['items'] ?? []) as $item) {
                $text = trim((string) $item);
                if ($text === '') {
                    continue;
                }
                $options[] = ['text' => $text, 'is_correct' => false, 'match_text' => null];
            }
            if (count($options) < 2) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least two items in the correct order.',
                ]);
            }
        } elseif ($type === 'short_answer' || $type === 'fill_blank') {
            if ($type === 'fill_blank' && $accepted === []) {
                throw ValidationException::withMessages([
                    'accepted_answers' => 'Add at least one accepted answer for the blank.',
                ]);
            }
            if ($type === 'short_answer' && ! $request->exists('requires_review')) {
                $requiresReview = true;
            }
        } elseif ($type === 'scenario' && ($data['scenario_mode'] ?? 'choices') === 'written') {
            if ($prompt === '') {
                throw ValidationException::withMessages([
                    'prompt' => 'Describe the scenario.',
                ]);
            }
            if (! $request->exists('requires_review')) {
                $requiresReview = true;
            }
        } else {
            $multiple = $type === 'multiple_answer'
                || (in_array($type, ['image', 'video', 'scenario'], true) && ($data['selection'] ?? 'single') === 'multiple');
            $built = self::choiceOptions(
                (array) ($data['options'] ?? []),
                $multiple,
                isset($data['correct_index']) ? (int) $data['correct_index'] : null,
                array_map('intval', (array) ($data['correct_indexes'] ?? [])),
            );
            $options = $built;
            if ($type === 'scenario' && $prompt === '') {
                throw ValidationException::withMessages([
                    'prompt' => 'Describe the scenario.',
                ]);
            }
        }

        if (in_array($type, ['image', 'video'], true)) {
            $mediaKind = $type === 'image' ? 'image' : 'video';
            $removeMedia = $request->boolean('remove_media');
            $hasExisting = is_string($existing?->media_path) && $existing->media_path !== '' && ! $removeMedia;
            if (! $mediaFile instanceof UploadedFile && ! $hasExisting) {
                throw ValidationException::withMessages([
                    'media' => $type === 'image' ? 'Add an image for this question.' : 'Add a video for this question.',
                ]);
            }
            if ($mediaFile instanceof UploadedFile) {
                $rules = $mediaKind === 'image'
                    ? ['required', 'file', 'image', 'mimes:jpeg,jpg,jfif,png,webp,gif', 'max:8192']
                    : ['required', 'file', 'mimes:mp4,mov,webm,m4v', 'max:51200'];
                $request->validate(['media' => $rules]);
            }
        } else {
            $mediaFile = null;
        }

        return [
            'question_type' => $type,
            'question_text' => trim($data['question_text']),
            'prompt' => $prompt !== '' ? $prompt : null,
            'points' => isset($data['points']) ? (int) $data['points'] : 1,
            'explanation' => $explanation !== '' ? $explanation : null,
            'requires_review' => $requiresReview,
            'accepted_answers' => $accepted,
            'options' => $options,
            'media_kind' => $mediaKind,
            'media_file' => $mediaFile instanceof UploadedFile ? $mediaFile : null,
            'remove_media' => $request->boolean('remove_media'),
        ];
    }

    /**
     * @param  list<mixed>  $rawOptions
     * @param  list<int>  $correctIndexes
     * @return list<array{text: string, is_correct: bool, match_text: string|null}>
     */
    private static function choiceOptions(array $rawOptions, bool $multiple, ?int $correctIndex, array $correctIndexes): array
    {
        $options = [];
        foreach ($rawOptions as $index => $option) {
            if (! is_array($option)) {
                continue;
            }
            $text = trim((string) ($option['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $source = (int) $index;
            $isCorrect = $multiple
                ? in_array($source, $correctIndexes, true)
                : $correctIndex === $source;
            $options[] = ['text' => $text, 'is_correct' => $isCorrect, 'match_text' => null];
        }

        if (count($options) < 2) {
            throw ValidationException::withMessages([
                'options' => 'Add at least two answer options.',
            ]);
        }

        $correctCount = count(array_filter($options, fn (array $option): bool => $option['is_correct']));
        if ($multiple && $correctCount < 1) {
            throw ValidationException::withMessages([
                'correct_indexes' => 'Mark at least one correct option.',
            ]);
        }
        if (! $multiple && $correctCount !== 1) {
            throw ValidationException::withMessages([
                'correct_index' => 'Mark which option is correct.',
            ]);
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private static function lines(?string $text): array
    {
        $rows = preg_split('/\r\n|\r|\n/', (string) $text) ?: [];
        $out = [];
        foreach ($rows as $row) {
            $row = trim((string) $row);
            if ($row === '') {
                continue;
            }
            $out[] = mb_substr($row, 0, 300);
            if (count($out) >= 20) {
                break;
            }
        }

        return $out;
    }
}
