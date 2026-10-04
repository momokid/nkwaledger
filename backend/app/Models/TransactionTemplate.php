<?php

namespace App\Models;

use App\Enums\StockSource;
use App\Exceptions\Ledger\PostingFailed;
use Illuminate\Database\Eloquent\Builder;
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

    // what the web form says for each refusal; sync reuses the same words
    public const NOT_FOUND = 'The selected transaction template id is invalid.';
    public const NOT_FOR_FARM = 'That kind of record does not match your farm.';
    public const NO_CREDIT = 'That kind of record cannot be put on credit.';

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
        'is_stock_purchase',
        'is_liability',
        'stock_source',
        'allows_credit',
    ];

    protected $attributes = [
        'settlement_side' => 'none',
        'requires_farm_unit' => false,
        'is_system' => false,
        'is_active' => true,
        'is_produce_sale' => false,
        'is_stock_purchase' => false,
        'is_liability' => false,
        'allows_credit' => false,
    ];

    protected function casts(): array
    {
        return [
            'requires_farm_unit' => 'boolean',
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'is_produce_sale' => 'boolean',
            'is_stock_purchase' => 'boolean',
            'is_liability' => 'boolean',
            'stock_source' => StockSource::class,
            'allows_credit' => 'boolean',
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

    // what a farmer may record: live, never an adjustment, and for a farm type theirs
    // (or for every farm, which is a template with no category)
    public function scopeAllowedFor(Builder $query, FarmerProfile $farmer): Builder
    {
        return $query->where('is_active', true)
            ->where('transaction_type', '!=', Transaction::ADJUSTMENT)
            ->where(fn($inner) => $inner
                ->whereIn('farm_type_category_id', $farmer->farmTypes()->pluck('category_id'))
                ->orWhereNull('farm_type_category_id'));
    }

    // why this farmer may not record against this template, or null when they may
    public static function refusalFor(mixed $id, FarmerProfile $farmer): ?string
    {
        $id = filter_var($id, FILTER_VALIDATE_INT);

        // withTrashed mirrors the plain table lookup the web form always did
        if ($id === false || ! static::withTrashed()->whereKey($id)->where('is_active', true)->exists()) {
            return self::NOT_FOUND;
        }

        return static::allowedFor($farmer)->whereKey($id)->exists() ? null : self::NOT_FOR_FARM;
    }

    public function creditRefusal(): ?string
    {
        return $this->allows_credit ? null : self::NO_CREDIT;
    }

    // the farmer never sees or chooses "Receivable"/"Payable" - which one applies
    // follows straight from whether money is coming in or going out
    public function creditSettlementAccountId(): int
    {
        $name = match ($this->transaction_type) {
            Transaction::INCOME => 'Accounts Receivable',
            Transaction::EXPENSE => 'Accounts Payable',
            default => throw PostingFailed::because(self::NO_CREDIT),
        };

        $accountId = LedgerAccount::where('name', $name)->value('id');

        if ($accountId === null) {
            throw PostingFailed::because('Credit is not set up yet.');
        }

        return $accountId;
    }

    protected function guardAgainstUnknownSettlementSide(): void
    {
        if (! in_array($this->settlement_side, self::SETTLEMENT_SIDES, true)) {
            throw new InvalidArgumentException('Unknown settlement side.');
        }
    }
}
