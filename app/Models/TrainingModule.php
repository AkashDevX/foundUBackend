<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'status',
    'is_induction',
    'pass_percent',
    'max_attempts',
    'question_time_seconds',
    'quiz_required',
    'allow_retakes',
    'issues_certificate',
    'certificate_validity_months',
    'created_by',
])]
class TrainingModule extends Model
{
    public function pages(): HasMany
    {
        return $this->hasMany(TrainingPage::class)->orderBy('sort_order')->orderBy('id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(TrainingQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrainingAssignment::class);
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    protected function casts(): array
    {
        return [
            'is_induction' => 'boolean',
            'pass_percent' => 'integer',
            'max_attempts' => 'integer',
            'question_time_seconds' => 'integer',
            'quiz_required' => 'boolean',
            'allow_retakes' => 'boolean',
            'issues_certificate' => 'boolean',
            'certificate_validity_months' => 'integer',
        ];
    }
}
