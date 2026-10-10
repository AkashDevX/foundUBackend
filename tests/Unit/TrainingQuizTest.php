<?php

namespace Tests\Unit;

use App\Models\TrainingAttempt;
use App\Models\TrainingAttemptAnswer;
use App\Models\TrainingQuestion;
use App\Models\TrainingQuestionOption;
use App\Support\TrainingQuiz;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TrainingQuizTest extends TestCase
{
    public function test_single_choice_and_multiple_choice_award_points_only_for_the_correct_option(): void
    {
        $question = $this->question('multiple_choice', [
            $this->option(1, 'Mop', true),
            $this->option(2, 'Broom', false),
        ], 2);

        $right = TrainingQuiz::grade($question, ['option_id' => 1]);
        $wrong = TrainingQuiz::grade($question, ['option_id' => 2]);
        $blank = TrainingQuiz::grade($question, []);

        $this->assertTrue($right['is_correct']);
        $this->assertSame(2, $right['points_awarded']);
        $this->assertFalse($wrong['is_correct']);
        $this->assertSame(0, $wrong['points_awarded']);
        $this->assertNull($blank['review_status']);
        $this->assertFalse($blank['is_correct']);
    }

    public function test_multiple_answer_requires_every_correct_option(): void
    {
        $question = $this->question('multiple_answer', [
            $this->option(1, 'Gloves', true),
            $this->option(2, 'Goggles', true),
            $this->option(3, 'Sandals', false),
        ]);

        $this->assertTrue(TrainingQuiz::grade($question, ['option_ids' => [1, 2]])['is_correct']);
        $this->assertFalse(TrainingQuiz::grade($question, ['option_ids' => [1]])['is_correct']);
        $this->assertFalse(TrainingQuiz::grade($question, ['option_ids' => [1, 2, 3]])['is_correct']);
    }

    public function test_true_false_yes_no_ordering_matching_and_fill_blank_are_scored(): void
    {
        $trueFalse = $this->question('true_false', [
            $this->option(1, 'True', true),
            $this->option(2, 'False', false),
        ]);
        $this->assertTrue(TrainingQuiz::grade($trueFalse, ['option_id' => 1])['is_correct']);

        $yesNo = $this->question('yes_no', [
            $this->option(3, 'Yes', false),
            $this->option(4, 'No', true),
        ]);
        $this->assertTrue(TrainingQuiz::grade($yesNo, ['option_id' => 4])['is_correct']);

        $order = $this->question('ordering', [
            $this->option(10, 'Dust', false, 0),
            $this->option(11, 'Mop', false, 1),
            $this->option(12, 'Dry', false, 2),
        ]);
        $this->assertTrue(TrainingQuiz::grade($order, ['order' => [10, 11, 12]])['is_correct']);
        $this->assertFalse(TrainingQuiz::grade($order, ['order' => [12, 11, 10]])['is_correct']);

        $match = $this->question('matching', [
            $this->option(20, 'Bleach', false, 0, 'Never mix with ammonia'),
            $this->option(21, 'Wet floor', false, 1, 'Use a caution sign'),
        ]);
        $this->assertTrue(TrainingQuiz::grade($match, [
            'matches' => [
                ['option_id' => 20, 'match_text' => 'never mix with ammonia'],
                ['option_id' => 21, 'match_text' => 'Use a caution sign'],
            ],
        ])['is_correct']);
        $this->assertFalse(TrainingQuiz::grade($match, [
            'matches' => [
                ['option_id' => 20, 'match_text' => 'Use a caution sign'],
                ['option_id' => 21, 'match_text' => 'Never mix with ammonia'],
            ],
        ])['is_correct']);

        $blank = $this->question('fill_blank', [], 1, ['caution sign']);
        $this->assertTrue(TrainingQuiz::grade($blank, ['text' => '  Caution   Sign '])['is_correct']);
        $this->assertFalse(TrainingQuiz::grade($blank, ['text' => 'cone'])['is_correct']);
    }

    public function test_short_answers_wait_for_review_unless_they_match_an_accepted_answer(): void
    {
        $question = $this->question('short_answer', [], 3, ['close the valve'], true);

        $exact = TrainingQuiz::grade($question, ['text' => 'Close the valve']);
        $other = TrainingQuiz::grade($question, ['text' => 'I would call a supervisor']);

        $this->assertTrue($exact['is_correct']);
        $this->assertNull($exact['review_status']);
        $this->assertSame(3, $exact['points_awarded']);
        $this->assertSame('pending', $other['review_status']);
        $this->assertSame(0, $other['points_awarded']);
    }

    public function test_training_is_completed_only_after_required_rules_are_met(): void
    {
        $this->assertSame('not_started', TrainingQuiz::assignmentStatus(null, true));
        $this->assertSame('failed', TrainingQuiz::assignmentStatus($this->attempt(false), true));
        $this->assertSame('completed', TrainingQuiz::assignmentStatus($this->attempt(true), true));
        $this->assertSame('completed', TrainingQuiz::assignmentStatus($this->attempt(false), false));
        $this->assertSame('completed', TrainingQuiz::assignmentStatus($this->attempt(true, waived: true), false));

        $pending = $this->attempt(null);
        $pending->setRelation('answers', collect([new TrainingAttemptAnswer(['review_status' => 'pending'])]));
        $this->assertSame('pending_review', TrainingQuiz::assignmentStatus($pending, true));
    }

    /**
     * @param  list<TrainingQuestionOption>  $options
     * @param  list<string>  $accepted
     */
    private function question(string $type, array $options, int $points = 1, array $accepted = [], bool $review = false): TrainingQuestion
    {
        $question = new TrainingQuestion([
            'question_type' => $type,
            'question_text' => 'Check',
            'points' => $points,
            'requires_review' => $review,
            'accepted_answers' => $accepted,
        ]);
        $question->setRelation('options', new Collection($options));

        return $question;
    }

    private function option(int $id, string $text, bool $correct, int $sort = 0, ?string $match = null): TrainingQuestionOption
    {
        $option = new TrainingQuestionOption([
            'option_text' => $text,
            'is_correct' => $correct,
            'sort_order' => $sort,
            'match_text' => $match,
        ]);
        $option->id = $id;

        return $option;
    }

    private function attempt(?bool $passed, bool $waived = false): TrainingAttempt
    {
        $attempt = new TrainingAttempt([
            'passed' => $passed,
            'waived' => $waived,
            'submitted_at' => now(),
            'materials_acknowledged_at' => now(),
        ]);
        $attempt->setRelation('answers', collect());

        return $attempt;
    }
}
