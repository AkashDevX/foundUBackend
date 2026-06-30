<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'work_location_id',
    'title',
    'description',
    'job_title_id',
    'shift_id',
    'scheduled_date',
    'start_time',
    'end_time',
    'notes',
    'created_by',
    'is_active',
])]
class EmployeeTaskAssignment extends Model
{
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
