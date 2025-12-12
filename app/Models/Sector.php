<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sector extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'menu_code',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'current_sector_id');
    }

    public function transferLogsFrom(): HasMany
    {
        return $this->hasMany(TransferLog::class, 'from_sector_id');
    }

    public function transferLogsTo(): HasMany
    {
        return $this->hasMany(TransferLog::class, 'to_sector_id');
    }
}
