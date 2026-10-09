<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'training_assignment_id',
    'training_module_id',
    'attempt_number',
    'score',
    'max_score',
    'percent',
    'passed',
    'submitted_at',
])]
class InductionAttempt extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(TrainingAssignment::class, 'training_assignment_id');
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    protected function casts(): array
    {
        return [
            'attempt_number' => 'integer',
            'score' => 'integer',
            'max_score' => 'integer',
            'percent' => 'float',
            'passed' => 'boolean',
            'submitted_at' => 'datetime',
        ];
    }
};
