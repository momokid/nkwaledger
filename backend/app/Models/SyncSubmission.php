<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SyncSubmission extends Model
{
    public const ACCEPTED = 'accepted';
    public const NEEDS_FIXING = 'needs_fixing';
    public const HELD = 'held_for_review';
    public const REJECTED = 'rejected';
    public const SUPERSEDED = 'superseded';

    protected $fillable = [
        'client_uuid',
        'user_id',
        'farmer_profile_id',
        'payload',
        'device_date',
        'received_at',
        'status',
        'reason',
        'transaction_id',
        'supersedes_id',
        'reviewed_by',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'device_date' => 'date',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    // the public address of a row, made here; client_uuid comes from the device and is never routed on
    protected static function booted(): void
    {
        static::creating(fn(self $submission) => $submission->uuid ??= (string) Str::uuid7());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // a value that is not a uuid never reaches the query, which PostgreSQL would reject
    public function resolveRouteBinding($value, $field = null)
    {
        return Str::isUuid($value) ? parent::resolveRouteBinding($value, $field) : null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
