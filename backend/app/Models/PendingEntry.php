<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use RuntimeException;

// what an agent recorded for a farmer, waiting for the farmer. It is the only place the state
// pending / accepted / declined / withdrawn is kept, and it is never deleted
class PendingEntry extends Model
{
    public const PENDING = 'pending';
    public const ACCEPTED = 'accepted';
    public const DECLINED = 'declined';
    public const WITHDRAWN = 'withdrawn';
    // the loan was no longer as the agent saw it, so the agent has to record it again
    public const REJECTED = 'rejected';

    public const LOAN_MONEY = 'loan.money';
    public const LOAN_EXISTING = 'loan.existing';
    public const LOAN_REPAYMENT = 'loan.repayment';

    public const VIA_APP = 'app';
    public const VIA_CODE = 'code';

    protected $fillable = [
        'uuid',
        'farmer_profile_id',
        'entry_type',
        'payload',
        'status',
        'recorded_by',
        'decided_by',
        'decided_via',
        'decided_at',
        'result_type',
        'result_id',
    ];

    protected $attributes = [
        'status' => self::PENDING,
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $entry) {
            $entry->uuid ??= (string) Str::uuid7();
        });

        static::deleting(function () {
            throw new RuntimeException('A pending entry is never deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function codes(): HasMany
    {
        return $this->hasMany(PendingEntryCode::class);
    }

    // the lender's name as the list has it now, never the name a payload was saved with
    public function lenderName(): string
    {
        $lender = $this->isRepayment()
            ? Loan::where('uuid', $this->payload['loan_uuid'])->first()?->lender
            : Lender::where('uuid', $this->payload['lender'])->first();

        return $lender?->name ?? '';
    }

    public function isRepayment(): bool
    {
        return $this->entry_type === self::LOAN_REPAYMENT;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }
}
