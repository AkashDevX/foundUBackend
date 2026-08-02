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
    'status',
    'leave_type_id',
    'leave_record_id',
    'created_by',
])]
class EmployeeScheduleShift extends Model
{
    public const TYPE_SHIFT = 'shift';

    public const TYPE_TIME_OFF = 'time_off';

    public const STATUS_SICK_CALL_OUT = 'sick_call_out';

    public const STATUS_NO_SHOW = 'no_show';

    /**
     * @return array<string, string>
     */
    public static function statusLabels(): array
    {
        return [
            self::STATUS_SICK_CALL_OUT => 'Sick call out',
            self::STATUS_NO_SHOW => 'No show',
        ];
    }

    public static function statusLabel(?string $status): ?string
    {
        return $status !== null ? (self::statusLabels()[$status] ?? null) : null;
    }

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

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function leaveRecord(): BelongsTo
    {
        return $this->belongsTo(EmployeeLeaveRecord::class, 'leave_record_id');
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
