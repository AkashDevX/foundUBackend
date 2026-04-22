<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['picklist_key', 'value', 'label', 'sort_order', 'is_active'])]
class RegistrationPicklistItem extends Model
{
    protected $connection = 'master';

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
