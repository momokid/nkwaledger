<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Loan extends Model
{
    public const KIND_MONEY = 'money';
    public const KIND_EXISTING = 'existing';

    // the only two states a loan itself has. Waiting, declined and withdrawn belong to the
    // pending entry that came before the loan, and are kept there and nowhere else
    public const DEFAULT_AFTER_DAYS = 90;

    public const ACTIVE = 'active';
    public const CANCELLED = 'cancelled';

    // shown with every loan: there is no lender check yet, so every loan is self-reported
    public const NOTICE = 'You entered this loan yourself. It has not been checked with the lender.';

    public const LENDER_TYPES = Lender::TYPES;

    protected $fillable = [
        'uuid',
        'farmer_profile_id',
        'kind',
        'status',
        'lender_id',
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

    public function lender(): BelongsTo
    {
        return $this->belongsTo(Lender::class);
    }

    // the name and type live on the shared lender record; these keep the many places that read them simple
    public function getLenderNameAttribute(): string
    {
        return $this->lender?->name ?? '';
    }

    public function getLenderTypeAttribute(): string
    {
        return $this->lender?->type ?? '';
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

    // why a purchase may not be marked "Paid from a loan"; null when it may. One text for every way the loan can be wrong, so it never says whether someone else's loan exists
    public static function refusalForPurchase(?self $loan, int $farmerId, ?int $farmUnitId, bool $isCredit, string $transactionType = Transaction::EXPENSE): ?string
    {
        if ($loan === null || $loan->farmer_profile_id !== $farmerId || $loan->kind !== self::KIND_MONEY || $loan->isCancelled()) {
            return 'Please choose one of your loans that is still active.';
        }

        if ($transactionType !== Transaction::EXPENSE) {
            return 'Only money spent can be paid from a loan.';
        }

        if ($isCredit) {
            return 'A purchase on credit cannot be paid from a loan.';
        }

        if ($farmUnitId === null) {
            return 'Please choose the farm this was bought for.';
        }

        return $loan->farmAllocations()->where('farm_unit_id', $farmUnitId)->exists() ? null : 'This farm is not on that loan.';
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    // the one place the balance is worked out: what is still owed, the principal and the interest together
    public function balanceMinor(): int
    {
        if ($this->isCancelled()) {
            return 0;
        }

        return $this->remainingPrincipalMinor() + $this->remainingInterestMinor();
    }

    /**
     * The balance of many loans at once, in two queries instead of two per loan. Must always agree with balanceMinor().
     *
     * @param Collection<int, Loan> $loans
     * @return Collection<int, int> loan id => balance in pesewas
     */
    public static function balancesOf(Collection $loans): Collection
    {
        $ids = $loans->pluck('id')->all();

        $paid = fn(string $link) => Transaction::excludeCancelled(
            DB::table('loan_repayments')
                ->join('transactions', 'transactions.id', '=', "loan_repayments.{$link}")
                ->whereIn('loan_repayments.loan_id', $ids)
        )->groupBy('loan_repayments.loan_id')->selectRaw('loan_repayments.loan_id as loan_id, SUM(transactions.amount_minor) as total')->pluck('total', 'loan_id');

        $principal = $paid('principal_transaction_id');
        $interest = $paid('interest_transaction_id');

        return $loans->mapWithKeys(fn(Loan $loan) => [
            $loan->id => $loan->isCancelled()
                ? 0
                : max(0, $loan->principal_minor - (int) ($principal[$loan->id] ?? 0)) + max(0, (int) $loan->agreed_interest_minor - (int) ($interest[$loan->id] ?? 0)),
        ]);
    }

    public function remainingPrincipalMinor(): int
    {
        return max(0, $this->principal_minor - $this->principalRepaidMinor());
    }

    public function remainingInterestMinor(): int
    {
        return max(0, (int) $this->agreed_interest_minor - $this->interestPaidMinor());
    }

    public function checks(): HasMany
    {
        return $this->hasMany(LoanCheck::class)->orderByDesc('id');
    }

    // the newest check with the lender, if anyone has made one
    public function currentCheck(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(LoanCheck::class)->latestOfMany('id');
    }

    public function farmAllocations(): HasMany
    {
        return $this->hasMany(LoanFarmAllocation::class);
    }

    // per farm: planned (what the farmer said at the start), accounted (live purchases paid from this loan on that farm),
    // and what is left. Worked out here and never stored. A farm with more bought than planned is over, not negative.
    // Amounts in pesewas; the page prop uses the farm's uuid, never its id.
    public function farmFigures(): Collection
    {
        $accounted = Transaction::query()->live()->where('loan_id', $this->id)
            ->groupBy('farm_unit_id')->selectRaw('farm_unit_id, SUM(amount_minor) as total')->pluck('total', 'farm_unit_id');

        return $this->farmAllocations()->with(['farmUnit:id,uuid,name', 'purposeTemplate:id,name'])->get()->map(function (LoanFarmAllocation $row) use ($accounted) {
            $spent = (int) ($accounted[$row->farm_unit_id] ?? 0);

            return [
                'uuid' => $row->farmUnit->uuid,
                'name' => $row->farmUnit->name,
                'purpose' => $row->purposeTemplate?->name ?? $row->purpose_note,
                'planned' => $row->amount_minor,
                'accounted' => $spent,
                'not_yet_accounted' => max(0, $row->amount_minor - $spent),
                'over' => max(0, $spent - $row->amount_minor),
            ];
        })->values();
    }

    public function otherUses(): HasMany
    {
        return $this->hasMany(LoanOtherUse::class);
    }

    // money the farmer named for a farm and has not yet said how it was used: the planned amounts less what was bought
    // and what was marked "something else". Worked out here, never stored. Nothing for a loan that is repaid,
    // cancelled, or has no farm amounts (older loans, existing loans, agent, USSD and offline loans)
    public function unaccountedMinor(): int
    {
        if ($this->kind !== self::KIND_MONEY || $this->isCancelled() || $this->balanceMinor() === 0) {
            return 0;
        }

        return max(0, (int) $this->farmFigures()->sum('not_yet_accounted') - (int) $this->otherUses()->sum('amount_minor'));
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

    // the word the list shows: active, overdue, default, repaid or cancelled
    public function displayStatus(): string
    {
        return self::statusFor($this->status, $this->isCancelled() ? 0 : $this->balanceMinor(), $this->due_date, today());
    }

    // never stored: worked out from the due date, today and what is still owed
    public static function statusFor(string $stored, int $balance, ?CarbonInterface $due, CarbonInterface $today): string
    {
        if ($stored === self::CANCELLED) {
            return self::CANCELLED;
        }

        if ($balance === 0) {
            return 'repaid';
        }

        if ($due === null) {
            return self::ACTIVE;
        }

        $day = $today->copy()->startOfDay();
        $dueDay = $due->copy()->startOfDay();

        if (! $day->gt($dueDay)) {
            return self::ACTIVE;
        }

        return $day->gte($dueDay->addDays(self::DEFAULT_AFTER_DAYS)) ? 'default' : 'overdue';
    }
}
