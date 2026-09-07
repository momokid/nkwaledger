<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TransactionTemplate extends Model
{
    use SoftDeletes;

    public const TYPES = ['INCOME', 'EXPENSE', 'LOSS', 'ADJUSTMENT'];

    public const SETTLEMENT_SIDES = ['debit', 'credit', 'none'];

    protected $fillable = [
        'name',
        'slug',
        'transaction_type',
        'debit_account_id',
        'credit_account_id',
        'settlement_side',
        'requires_farm_unit',
        'farm_type_category_id',
        'is_system',
        'is_active',
        'is_produce_sale',
    ];

    protected $attributes = [
        'settlement_side' => 'none',
        'requires_farm_unit' => false,
        'is_system' => false,
        'is_active' => true,
        'is_produce_sale' => false,
    ];

    protected function casts(): array
    {
        return [
            'requires_farm_unit' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_produce_sale' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $template) {
            $template->guardAgainstSameAccount();
            $template->guardAgainstUnknownType();
            $template->guardAgainstUnknownSettlementSide();
        });
    }

    public function debitAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'debit_account_id');
    }

    public function creditAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'credit_account_id');
    }

    public function farmTypeCategory(): BelongsTo
    {
        return $this->belongsTo(FarmTypeCategory::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    // once a record points at it, moving the accounts changes what happens next
    public function isUsed(): bool
    {
        return $this->transactions()->exists();
    }

    // the words are for the farmer to read, the accounts are the books
    public function accountingIsLocked(): bool
    {
        return $this->is_system || $this->isUsed();
    }

    // money cannot move from an account into itself
    protected function guardAgainstSameAccount(): void
    {
        if ($this->debit_account_id === null || $this->credit_account_id === null) {
            return;
        }

        if ((int) $this->debit_account_id === (int) $this->credit_account_id) {
            throw new InvalidArgumentException('A template cannot debit and credit the same ledger account.');
        }
    }

    protected function guardAgainstUnknownType(): void
    {
        if (! in_array($this->transaction_type, self::TYPES, true)) {
            throw new InvalidArgumentException('Unknown transaction type.');
        }
    }

    protected function guardAgainstUnknownSettlementSide(): void
    {
        if (! in_array($this->settlement_side, self::SETTLEMENT_SIDES, true)) {
            throw new InvalidArgumentException('Unknown settlement side.');
        }
    }
}
