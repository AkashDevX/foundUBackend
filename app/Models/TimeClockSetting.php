<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'grace_minutes',
    'outside_policy',
    'break_rule_enabled',
    'break_window_start_minutes',
    'break_window_end_minutes',
    'break_required_after_minutes',
    'break_reminder_lead_minutes',
])]
class TimeClockSetting extends Model
{
    protected function casts(): array
    {
        return [
            'grace_minutes' => 'integer',
            'break_rule_enabled' => 'boolean',
            'break_window_start_minutes' => 'integer',
            'break_window_end_minutes' => 'integer',
            'break_required_after_minutes' => 'integer',
            'break_reminder_lead_minutes' => 'integer',
        ];
    }
}
