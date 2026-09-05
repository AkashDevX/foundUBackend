<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'conversation_id',
    'participant_type',
    'participant_id',
    'last_read_at',
    'left_at',
])]
class ConversationParticipant extends Model
{
    public const TYPE_EMPLOYEE = 'employee';

    public const TYPE_COMPANY_ADMIN = 'company_admin';

    /** Sentinel participant_id for the shared company admin mailbox. */
    public const COMPANY_ADMIN_ID = 0;

    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'left_at' => 'datetime',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isCompanyAdmin(): bool
    {
        return $this->participant_type === self::TYPE_COMPANY_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->left_at === null;
    }
}
