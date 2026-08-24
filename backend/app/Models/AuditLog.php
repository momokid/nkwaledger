<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

#[Fillable([
    'user_id',
    'action',
    'auditable_type',
    'auditable_id',
    'old_values',
    'new_values',
    'ip_address',
    'user_agent',
])]
class AuditLog extends Model
{
    // a record of what happened has no later state to track
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        // an audit trail that can be rewritten proves nothing
        static::updating(function () {
            throw new RuntimeException('An audit entry cannot be changed once written.');
        });

        static::deleting(function () {
            throw new RuntimeException('An audit entry cannot be deleted.');
        });

        // the newest activity is what anyone opens this table to see
        static::addGlobalScope('recent', function (Builder $query) {
            $query->orderByDesc('created_at')->orderByDesc('id');
        });
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // points at whatever record the action touched, whichever table it lives in
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
