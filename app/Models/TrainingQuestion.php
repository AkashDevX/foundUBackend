<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'training_module_id',
    'question_text',
    'sort_order',
    'points',
])]
class TrainingQuestion extends Model
{
    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(TrainingQuestionOption::class)->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
