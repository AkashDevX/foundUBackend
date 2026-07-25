<?php

namespace App\Models;

use App\Support\ShiftBreaks;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'start_time', 'end_time', 'shift_days', 'breaks_summary', 'breaks', 'notes', 'is_active'])]
class Shift extends Model
{
    protected function casts(): array
    {
        return [
            'start_time' => 'datetime',
            'end_time' => 'datetime',
            'shift_days' => 'array',
            'breaks' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return list<array{label: string, minutes: int, paid: bool}>
     */
    public function normalizedBreaks(): array
    {
        return ShiftBreaks::normalize($this->breaks);
    }

    public function unpaidBreakMinutesFromTemplate(): int
    {
        return ShiftBreaks::unpaidMinutesTotal($this->normalizedBreaks());
    }
}
