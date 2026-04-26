<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'start_time', 'end_time', 'shift_days', 'breaks_summary', 'notes', 'is_active'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'shift_days' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
