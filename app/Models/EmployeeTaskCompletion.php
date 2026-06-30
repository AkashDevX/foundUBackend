<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'employee_task_assignment_id',
    'completion_date',
    'completed_at',
])]
class EmployeeTaskCompletion extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function employeeTaskAssignment(): BelongsTo
    {
        return $this->belongsTo(EmployeeTaskAssignment::class);
    }

    protected function casts(): array
    {
        return [
            'completion_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }
}
