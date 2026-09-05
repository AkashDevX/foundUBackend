<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'employee_id',
    'policy_version',
    'accepted_at',
])]
class MessagingPolicyAcceptance extends Model
{
    protected function casts(): array
    {
        return [
            'policy_version' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
