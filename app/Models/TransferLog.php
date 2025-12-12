<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferLog extends Model
{
    protected $fillable = [
        'conversation_id',
        'from_sector_id',
        'to_sector_id',
        'from_agent_id',
        'to_agent_id',
        'note',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function fromSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'from_sector_id');
    }

    public function toSector(): BelongsTo
    {
        return $this->belongsTo(Sector::class, 'to_sector_id');
    }

    public function fromAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_agent_id');
    }

    public function toAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_agent_id');
    }
}
