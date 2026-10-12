<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Loan extends Model
{
    public const KIND_MONEY = 'money';
    public const KIND_EXISTING = 'existing';

    // the only two states a loan itself has. Waiting, declined and withdrawn belong to the
    // pending entry that came before the loan, and are kept there and nowhere else
    public const ACTIVE = 'active';
    public const CANCELLED = 'cancelled';

    // shown with every loan: there is no lender check yet, so every loan is self-reported
    public const NOTICE = 'You entered this loan yourself. It has not been checked with the lender.';

    public const LENDER_TYPES = ['bank', 'microfinance', 'group', 'individual', 'other'];

    protected $fillable = [
        'uuid',
        'farmer_profile_id',
        'kind',
        'status',
        'lender_name',
        'lender_type',
        'principal_minor',
        'agreed_interest_minor',
        'loan_date',
        'due_date',
        'note',
        'settlement_account_id',
        'transaction_id',
        'recorded_by',
        'accepted_via',
        'idempotency_key',
    ];

    protected $attributes = [
        'status' => self::ACTIVE,
    ];

    protected function casts(): array
    {
        return [
            'principal_minor' => 'integer',
            'agreed_interest_minor' => 'integer',
            'loan_date' => 'date',
            'due_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $loan) {
            $loan->uuid ??= (string) Str::uuid7();
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

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function settlementAccount(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'settlement_account_id');
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    // the one place the balance is worked out: the principal less what has been paid back
    public function balanceMinor(): int
    {
        if ($this->isCancelled()) {
            return 0;
        }

        return $this->remainingPrincipalMinor();
    }

    public function remainingPrincipalMinor(): int
    {
        return max(0, $this->principal_minor - $this->principalRepaidMinor());
    }

    public function remainingInterestMinor(): int
    {
        return max(0, (int) $this->agreed_interest_minor - $this->interestPaidMinor());
    }

    public function repayments(): HasMany
    {
        return $this->hasMany(LoanRepayment::class);
    }

    // paid so far is read from the ledger records, never from a counter; a cancelled record is not counted
    public function principalRepaidMinor(): int
    {
        return $this->paidMinor('principal_transaction_id');
    }

    public function interestPaidMinor(): int
    {
        return $this->paidMinor('interest_transaction_id');
    }

    private function paidMinor(string $link): int
    {
        if ($this->id === null) {
            return 0;
        }

        $query = DB::table('loan_repayments')
            ->join('transactions', 'transactions.id', '=', "loan_repayments.{$link}")
            ->where('loan_repayments.loan_id', $this->id);

        return (int) Transaction::excludeCancelled($query)->sum('transactions.amount_minor');
    }

    // "Repaid" is never stored: it is a loan that was in force and now owes nothing
    public function isRepaid(): bool
    {
        return $this->status === self::ACTIVE && $this->balanceMinor() === 0;
    }

    // the word the list shows: active, repaid or cancelled
    public function displayStatus(): string
    {
        if ($this->isCancelled()) {
            return self::CANCELLED;
        }

        return $this->isRepaid() ? 'repaid' : self::ACTIVE;
    }
}
