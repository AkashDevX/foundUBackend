<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_page_id',
    'training_page_section_id',
    'kind',
    'label',
    'body',
    'file_path',
    'sort_order',
])]
class TrainingSlideBlock extends Model
{
    public const KINDS = ['text', 'pdf', 'photo', 'video', 'link', 'instruction', 'note'];

    public function page(): BelongsTo
    {
        return $this->belongsTo(TrainingPage::class, 'training_page_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(TrainingPageSection::class, 'training_page_section_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
