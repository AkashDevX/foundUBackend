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

    /**
     * @return list<string>
     */
    public static function activeValues(string $picklistKey): array
    {
        return self::query()
            ->where('picklist_key', $picklistKey)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->pluck('value')
            ->all();
    }
}
