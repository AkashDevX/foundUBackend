<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'training_assignment_id',
    'employee_id',
    'materials_acknowledged_at',
    'question_order',
    'option_order',
    'score',
    'max_score',
    'percent',
    'passed',
    'submitted_at',
])]
class TrainingAttempt extends Model
{
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class, 'training_assignment_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(TrainingAttemptAnswer::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function materialsAcknowledged(): bool
    {
        return $this->materials_acknowledged_at !== null;
    }

    protected function casts(): array
    {
        return [
            'materials_acknowledged_at' => 'datetime',
            'submitted_at' => 'datetime',
            'question_order' => 'array',
            'option_order' => 'array',
            'score' => 'integer',
            'max_score' => 'integer',
            'percent' => 'float',
            'passed' => 'boolean',
        ];
    }
}
