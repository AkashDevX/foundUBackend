<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'employment_type',
    'award_level',
    'rate_type',
    'amount',
    'effective_from',
])]
class PayrollAwardRate extends Model
{
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'effective_from' => 'date',
        ];
    }
}
