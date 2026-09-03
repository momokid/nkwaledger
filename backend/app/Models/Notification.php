<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'user_id',
        'kind',
        'message',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $note) {
            $note->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function markRead(): void
    {
        if ($this->isRead()) {
            return;
        }

        $this->forceFill(['read_at' => now()])->save();
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
