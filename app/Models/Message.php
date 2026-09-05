<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'conversation_id',
    'sender_type',
    'sender_id',
    'body',
    'message_type',
    'attachment_path',
    'attachment_mime',
    'attachment_name',
    'attachment_size',
])]
class Message extends Model
{
    use SoftDeletes;

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_FILE = 'file';

    public const TYPE_SYSTEM = 'system';

    protected function casts(): array
    {
        return [
            'attachment_size' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(MessageReport::class);
    }

    public function hasAttachment(): bool
    {
        return is_string($this->attachment_path) && $this->attachment_path !== '';
    }
}
