<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'leave_type',
    'leave_date',
    'hours',
    'hourly_rate',
    'loading_percent',
    'paid_amount',
    'status',
    'payroll_run_id',
    'notes',
    'created_by',
])]
class EmployeeLeaveRecord extends Model
{
    public const TYPE_SICK = 'sick';

    public const TYPE_ANNUAL = 'annual';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_CANCELLED = 'cancelled';

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payrollRun(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class);
    }

    protected function casts(): array
    {
        return [
            'leave_date' => 'date',
            'hours' => 'decimal:2',
            'hourly_rate' => 'decimal:2',
            'loading_percent' => 'decimal:2',
            'paid_amount' => 'decimal:2',
        ];
    }
}
