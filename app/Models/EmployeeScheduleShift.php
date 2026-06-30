<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'scheduled_date',
    'entry_type',
    'start_time',
    'end_time',
    'shift_id',
    'job_title_id',
    'department_id',
    'work_location_id',
    'notes',
    'created_by',
])]
class EmployeeScheduleShift extends Model
{
    public const TYPE_SHIFT = 'shift';

    public const TYPE_TIME_OFF = 'time_off';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function jobTitle(): BelongsTo
    {
        return $this->belongsTo(JobTitle::class, 'job_title_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workLocation(): BelongsTo
    {
        return $this->belongsTo(WorkLocation::class);
    }

    protected function casts(): array
    {
        return [
            'scheduled_date' => 'date',
            'start_time' => 'datetime:H:i',
            'end_time' => 'datetime:H:i',
        ];
    }
}
