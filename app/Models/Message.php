<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id',
        'direction',
        'type',
        'body',
        'media_url',
        'sent_at',
        'raw_payload',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'raw_payload' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function isFromClient(): bool
    {
        return $this->direction === 'client';
    }

    public function isFromAgent(): bool
    {
        return $this->direction === 'agent';
    }

    public function isFromBot(): bool
    {
        return $this->direction === 'bot';
    }
}
