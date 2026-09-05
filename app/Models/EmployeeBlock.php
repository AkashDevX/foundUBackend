<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'blocker_employee_id',
    'blocked_employee_id',
])]
class EmployeeBlock extends Model
{
    public function blocker(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'blocker_employee_id');
    }

    public function blocked(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'blocked_employee_id');
    }
}
