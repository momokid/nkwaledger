<?php

namespace App\Models;

use App\Enums\ContactRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

// a buyer's "I'm interested" against anything contactable - a produce listing today,
// never assumed to be the only kind; the listing itself is never printed as a phone
// number, this is the whole point of the indirection. Neither side's number is ever
// exposed until the recipient replies - gated here, at the model, so every reader
// (props, API, a future export) gets the same guarantee without remembering to check
#[Fillable([
    'contactable_type',
    'contactable_id',
    'requester_user_id',
    'recipient_user_id',
    'message',
    'sender_phone',
    'status',
    'replied_at',
    'reply_message',
    'expires_at',
])]
class ContactRequest extends Model
{
    use HasFactory;

    protected $attributes = [
        'status' => 'sent',
    ];

    protected function casts(): array
    {
        return [
            'status' => ContactRequestStatus::class,
            'replied_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ContactRequest $request) {
            if ($request->uuid === null) {
                $request->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isSent(): bool
    {
        return $this->status === ContactRequestStatus::Sent;
    }

    public function isExpired(): bool
    {
        return $this->status === ContactRequestStatus::Expired
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    // never trust status alone - a request whose expiry has passed but hasn't been
    // swept by the scheduled command yet must still behave as expired everywhere
    public function canBeReplied(): bool
    {
        return $this->isSent() && ! $this->isExpired();
    }

    private function isReplied(): bool
    {
        return $this->status === ContactRequestStatus::Replied;
    }

    // null until replied, whatever the caller is - a controller cannot forget to
    // check this, because there is nothing else to read the number from
    public function revealedSenderPhone(): ?string
    {
        return $this->isReplied() ? $this->sender_phone : null;
    }

    public function revealedRecipientPhone(): ?string
    {
        return $this->isReplied() ? $this->recipient?->phone : null;
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
