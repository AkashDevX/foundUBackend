<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'training_page_id',
    'title',
    'body',
    'image_path',
    'content_order',
    'sort_order',
])]
class TrainingPageSection extends Model
{
    public function page(): BelongsTo
    {
        return $this->belongsTo(TrainingPage::class, 'training_page_id');
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(TrainingSlideBlock::class, 'training_page_section_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'content_order' => 'array',
        ];
    }
}
