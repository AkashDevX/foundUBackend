<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'content',
    'last_updated_on',
])]
class TermsAndConditions extends Model
{
    protected $table = 'terms_and_conditions';

    protected function casts(): array
    {
        return [
            'last_updated_on' => 'date',
        ];
    }
}
