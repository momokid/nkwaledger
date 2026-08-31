<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use HasFactory;

    public const INCOME = 'INCOME';
    public const EXPENSE = 'EXPENSE';
    public const LOSS = 'LOSS';
    public const ADJUSTMENT = 'ADJUSTMENT';

    public const TYPES = [self::INCOME, self::EXPENSE, self::LOSS, self::ADJUSTMENT];

    public const CHANNELS = ['web', 'mobile', 'ussd'];

    // a row here is written once, so there is nothing to stamp on update
    public const UPDATED_AT = null;

    protected $fillable = [
        'uuid',
        'reference',
        'farmer_profile_id',
        'transaction_template_id',
        'transaction_type',
        'accounting_period_id',
        'transaction_date',
        'amount_minor',
        'settlement_account_id',
        'farm_unit_id',
        'narration',
        'channel',
        'is_provisional',
        'reverses_transaction_id',
        'recorded_by',
        'idempotency_key',
        'posted_at',
    ];

    protected $attributes = [
        'is_provisional' => false,
    ];

    protected function casts(): array
    {
        return [
            'transaction_date' => 'date',
            'amount_minor' => 'integer',
            'is_provisional' => 'boolean',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $transaction) {
            $transaction->uuid ??= (string) Str::uuid7();
            $transaction->reference ??= self::freshReference();

            $transaction->guardAgainstUnknownType();
            $transaction->guardAgainstUnknownChannel();
            $transaction->guardAgainstEmptyAmount();
            $transaction->guardAgainstBadReversal();
        });

        static::updating(function () {
            throw new RuntimeException('A transaction cannot be changed once it is written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A transaction cannot be deleted.');
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(TransactionTemplate::class, 'transaction_template_id');
    }

    public function accountingPeriod(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class);
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'settlement_account_id');
    }

    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    // the original this one puts right
    public function reverses(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reverses_transaction_id');
    }

    // the correction that put this one right, if there ever was one
    public function reversedBy(): HasOne
    {
        return $this->hasOne(self::class, 'reverses_transaction_id');
    }

    public function reversalRequests(): HasMany
    {
        return $this->hasMany(ReversalRequest::class);
    }

    public function isAdjustment(): bool
    {
        return $this->transaction_type === self::ADJUSTMENT;
    }

    // twelve digits, tried again if the unique index rejects it
    protected static function freshReference(): string
    {
        return str_pad((string) random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
    }

    protected function guardAgainstUnknownType(): void
    {
        if (! in_array($this->transaction_type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown transaction type.');
        }
    }

    protected function guardAgainstUnknownChannel(): void
    {
        if (! in_array($this->channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Unknown channel.');
        }
    }

    // the plus or minus lives in the journal lines, never here
    protected function guardAgainstEmptyAmount(): void
    {
        if ((int) $this->amount_minor <= 0) {
            throw new InvalidArgumentException('An amount must be more than zero.');
        }
    }

    protected function guardAgainstBadReversal(): void
    {
        if ($this->reverses_transaction_id === null) {
            return;
        }

        if (! $this->isAdjustment()) {
            throw new InvalidArgumentException('Only an adjustment can reverse a transaction.');
        }

        $original = self::query()->find($this->reverses_transaction_id);

        if ($original === null) {
            throw new InvalidArgumentException('The transaction being reversed does not exist.');
        }

        // a correction of a correction hides the trail
        if ($original->isAdjustment()) {
            throw new InvalidArgumentException('An adjustment cannot be adjusted.');
        }
    }
}
