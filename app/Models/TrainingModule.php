<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title',
    'description',
    'status',
    'pass_percent',
    'question_time_seconds',
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
            'pass_percent' => 'integer',
            'question_time_seconds' => 'integer',
        ];
    }
}
