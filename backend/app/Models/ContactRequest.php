<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

// a buyer's "I'm interested" against anything contactable - a produce listing today,
// never assumed to be the only kind; the listing itself is never printed as a phone
// number, this is the whole point of the indirection
#[Fillable([
    'contactable_type',
    'contactable_id',
    'requester_user_id',
    'recipient_user_id',
    'message',
])]
class ContactRequest extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ContactRequest $request) {
            if ($request->uuid === null) {
                $request->uuid = (string) Str::uuid7();
            }
        });
    }

    public function contactable(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
