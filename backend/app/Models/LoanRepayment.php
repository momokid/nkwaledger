<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

// the amount a farmer paid back and the two records it became. Written once, never edited or deleted
class LoanRepayment extends Model
{
    protected $fillable = [
        'uuid',
        'loan_id',
        'farmer_profile_id',
        'amount_minor',
        'principal_minor',
        'interest_minor',
        'principal_transaction_id',
        'interest_transaction_id',
        'paid_from_account_id',
        'repaid_on',
        'excess_confirmed',
        'recorded_by',
        'accepted_via',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'principal_minor' => 'integer',
            'interest_minor' => 'integer',
            'repaid_on' => 'date',
            'excess_confirmed' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $repayment) {
            $repayment->uuid ??= (string) Str::uuid7();
        });

        static::updating(function () {
            throw new RuntimeException('A repayment cannot be changed once it is written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A repayment cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function principalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'principal_transaction_id');
    }

    public function interestTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'interest_transaction_id');
    }
}
