<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
