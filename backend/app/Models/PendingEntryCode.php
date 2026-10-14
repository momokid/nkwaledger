<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// one code sent for one pending entry. Only a keyed hash is kept, never the code
class PendingEntryCode extends Model
{
    // created_at is set by hand from now(), so a clock that is moved moves it too
    public $timestamps = false;

    protected $fillable = [
        'pending_entry_id',
        'code_hash',
        'expires_at',
        'attempts',
        'used_at',
        'cancelled_at',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    protected $hidden = ['code_hash'];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(PendingEntry::class, 'pending_entry_id');
    }
}
