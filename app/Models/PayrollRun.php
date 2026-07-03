<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'fortnight_start',
    'fortnight_end',
    'status',
    'generated_at',
    'generated_by',
    'notes',
])]
class PayrollRun extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_FINALIZED = 'finalized';

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollRunLine::class);
    }

    protected function casts(): array
    {
        return [
            'fortnight_start' => 'date',
            'fortnight_end' => 'date',
            'generated_at' => 'datetime',
        ];
    }
}
