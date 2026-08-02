<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'code',
    'is_paid',
    'default_annual_hours',
    'requires_approval',
    'is_active',
    'sort_order',
    'notes',
    'created_by',
])]
class LeaveType extends Model
{
    public function leaveRecords(): HasMany
    {
        return $this->hasMany(EmployeeLeaveRecord::class, 'leave_type', 'code');
    }

    protected function casts(): array
    {
        return [
            'is_paid' => 'boolean',
            'default_annual_hours' => 'decimal:2',
            'requires_approval' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
