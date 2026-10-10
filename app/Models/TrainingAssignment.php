<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'training_module_id',
    'employee_id',
    'assigned_by',
    'assigned_at',
    'due_date',
])]
class TrainingAssignment extends Model
{
    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(TrainingAttempt::class)->orderBy('id');
    }

    public function attempt(): HasOne
    {
        return $this->hasOne(TrainingAttempt::class)->latestOfMany();
    }

    public function certificate(): HasOne
    {
        return $this->hasOne(TrainingCertificate::class);
    }

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'due_date' => 'date',
        ];
    }
}
