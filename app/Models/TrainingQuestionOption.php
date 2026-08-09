<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_question_id',
    'option_text',
    'is_correct',
    'sort_order',
])]
class TrainingQuestionOption extends Model
{
    public function question(): BelongsTo
    {
        return $this->belongsTo(TrainingQuestion::class, 'training_question_id');
    }

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
