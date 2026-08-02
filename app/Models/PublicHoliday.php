<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'holiday_date',
    'name',
    'region',
])]
class PublicHoliday extends Model
{
    protected function casts(): array
    {
        return [
            'holiday_date' => 'date',
        ];
    }
}
