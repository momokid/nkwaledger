<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

// a request for a lender that is not in the list. It waits, then the admin adds the lender, points it at one already
// in the list, or rejects it; after that it is never changed
class LenderRequest extends Model
{
    public const PENDING = 'pending';
    public const ADDED = 'added';
    public const MERGED = 'merged';
    public const REJECTED = 'rejected';

    protected $fillable = ['uuid', 'farmer_profile_id', 'requested_by', 'name', 'name_key', 'type', 'note', 'status', 'lender_id', 'decision_note', 'decided_by', 'decided_at'];

    protected $casts = ['decided_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (self $request) {
            $request->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }
}
