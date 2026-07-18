<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'shift_id',
    'unpaid_break_minutes',
    'sort_order',
])]
class EmployeeAssignmentShift extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    protected function casts(): array
    {
        return [
            'unpaid_break_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
