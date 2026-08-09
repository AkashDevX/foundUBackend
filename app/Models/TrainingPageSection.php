<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'training_page_id',
    'title',
    'body',
    'sort_order',
])]
class TrainingPageSection extends Model
{
    public function page(): BelongsTo
    {
        return $this->belongsTo(TrainingPage::class, 'training_page_id');
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
