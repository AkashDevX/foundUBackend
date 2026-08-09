<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'training_module_id',
    'title',
    'body',
    'sort_order',
])]
class TrainingPage extends Model
{
    public function module(): BelongsTo
    {
        return $this->belongsTo(TrainingModule::class, 'training_module_id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TrainingPageSection::class)->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
