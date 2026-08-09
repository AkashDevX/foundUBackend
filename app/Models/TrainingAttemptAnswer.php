<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_attempt_id',
    'training_question_id',
    'selected_option_id',
    'is_correct',
])]
class TrainingAttemptAnswer extends Model
{
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(TrainingAttempt::class, 'training_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestion::class, 'training_question_id');
    }

    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestionOption::class, 'selected_option_id');
    }

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }
}
