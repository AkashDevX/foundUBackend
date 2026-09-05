<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'content',
    'version',
    'last_updated_on',
])]
class MessagingPolicy extends Model
{
    protected $table = 'messaging_policies';

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'last_updated_on' => 'date',
        ];
    }
}
