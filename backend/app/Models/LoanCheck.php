<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use RuntimeException;

// what an admin found when they checked a loan with its lender, and the note they left. Written once, never edited or deleted
class LoanCheck extends Model
{
    public const UPDATED_AT = null;

    public const STATUSES = ['confirmed', 'not_confirmed', 'does_not_match', 'unreachable'];

    protected $fillable = ['uuid', 'loan_id', 'recorded_by', 'status', 'note', 'idempotency_key'];

    protected static function booted(): void
    {
        static::creating(function (self $check) {
            $check->uuid ??= (string) Str::uuid7();
        });

        static::updating(function () {
            throw new RuntimeException('A check with the lender cannot be changed once it is written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A check with the lender cannot be deleted.');
        });
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}