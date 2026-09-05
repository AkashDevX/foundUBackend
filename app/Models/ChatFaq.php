<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'label',
    'icon',
    'question',
    'answer',
    'keywords',
    'sort_order',
    'is_active',
    'created_by',
])]
class ChatFaq extends Model
{
    protected $table = 'chat_faqs';

    /**
     * Feather icon names allowed in the admin form and mobile app.
     *
     * @return list<string>
     */
    public static function allowedIcons(): array
    {
        return \App\Support\ChatFaqIcons::names();
    }

    /**
     * @return array{id: int, label: string, icon: string, question: string, answer: string, keywords: list<string>}
     */
    public function toApiArray(): array
    {
        $keywords = $this->keywords;
        if (! is_array($keywords)) {
            $keywords = [];
        }

        return [
            'id' => (int) $this->id,
            'label' => (string) $this->label,
            'icon' => (string) ($this->icon ?: 'help-circle'),
            'question' => (string) $this->question,
            'answer' => (string) $this->answer,
            'keywords' => array_values(array_map('strval', $keywords)),
        ];
    }

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
