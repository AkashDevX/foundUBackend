<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'grace_minutes',
    'outside_policy',
])]
class TimeClockSetting extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
        ];
    }
}
