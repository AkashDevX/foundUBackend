<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'original_employee_id',
    'scheduled_date',
    'entry_type',
    'start_time',
    'end_time',
    'shift_id',
    'job_title_id',
    'department_id',
    'work_location_id',
    'notes',
    'recurrence_series_id',
    'recurrence_mode',
    'recurrence_starts',
    'recurrence_until',
    'recurrence_days',
    'status',
    'cover_status',
    'covered_from_shift_id',
    'covering_shift_id',
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

    public const COVER_LEAVE_UNCOVERED = 'leave_uncovered';

    public const COVER_UNASSIGNED = 'unassigned';

    public const COVER_ASSIGNED = 'assigned';

    public const COVER_ACTION_ASSIGN_EMPLOYEE = 'assign_employee';

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

    /**
     * @return array<string, string>
     */
    public static function coverStatusLabels(): array
    {
        return [
            self::COVER_LEAVE_UNCOVERED => 'Leave uncovered',
            self::COVER_UNASSIGNED => 'Unassigned',
            self::COVER_ASSIGNED => 'Covered',
        ];
    }

    public static function coverStatusLabel(?string $coverStatus): ?string
    {
        return $coverStatus !== null ? (self::coverStatusLabels()[$coverStatus] ?? null) : null;
    }

    /**
     * @return array<string, string>
     */
    public static function coverActionOptions(): array
    {
        return [
            self::COVER_LEAVE_UNCOVERED => 'Leave uncovered',
            self::COVER_ACTION_ASSIGN_EMPLOYEE => 'Assign to an employee',
            self::COVER_UNASSIGNED => 'Make unassigned',
        ];
    }

    /**
     * @return list<string>
     */
    public static function coverActionValues(): array
    {
        return array_keys(self::coverActionOptions());
    }

    public function needsCover(): bool
    {
        return in_array($this->cover_status, [self::COVER_LEAVE_UNCOVERED, self::COVER_UNASSIGNED], true);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function originalEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'original_employee_id');
    }

    public function coveredFromShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'covered_from_shift_id');
    }

    public function coveringShift(): BelongsTo
    {
        return $this->belongsTo(self::class, 'covering_shift_id');
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
            'recurrence_starts' => 'date',
            'recurrence_until' => 'date',
            'recurrence_days' => 'array',
        ];
    }
}
