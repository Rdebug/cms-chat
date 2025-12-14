<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'client_name',
        'current_sector_id',
        'current_agent_id',
        'status',
        'bot_state',
        'bot_last_prompt_at',
        'bot_menu_sent_at',
        'bot_clarification_context',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
            'bot_last_prompt_at' => 'datetime',
            'bot_menu_sent_at' => 'datetime',
            'bot_clarification_context' => 'array',
        ];
    }

    public function currentSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'current_sector_id');
    }

    public function currentAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_agent_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function transferLogs(): HasMany
    {
        return $this->hasMany(TransferLog::class);
    }

    public function isOpen(): bool
    {
        return !in_array($this->status, ['closed', 'archived']);
    }

    public function isNew(): bool
    {
        return $this->status === 'new';
    }

    public function isQueued(): bool
    {
        return $this->status === 'queued';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }
}
