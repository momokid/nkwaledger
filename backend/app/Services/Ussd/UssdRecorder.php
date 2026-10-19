<?php

namespace App\Services\Ussd;

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\FarmerEligibilityService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Services\RecordLock;
use Illuminate\Support\Collection;

// money in or out from a phone, posted the way the web posts it: everyday records, and farm sales with a unit and a quantity
class UssdRecorder
{
    private const SLUGS = [
        'income' => ['grant_income', 'hire_income', 'byproduct_income', 'other_income'],
        'expense' => ['labour_cost', 'transport_cost', 'rent_cost', 'utilities_cost', 'repairs_cost'],
        'sale' => ['produce_sale', 'animal_sale', 'produce_of_animal_sale', 'fish_sale'],
    ];
    private const METHODS = ['1' => 'Cash A/C', '2' => 'Momo A/C', '3' => 'Bank A/C'];
    private const CREDIT = '4';
    private const MAX_CEDIS = 100000;
    private const UNITS_SHOWN = 5;

    public function __construct(
        private readonly PostingService $posting,
        private readonly FarmerEligibilityService $eligibility,
        private readonly AccessControlService $access,
        private readonly RecordLock $lock,
    ) {}

    // the reason this farmer cannot record, in the web's own words, or null
    public function blockedReason(User $user): ?string
    {
        if (! $this->access->can($user, 'transactions.create')) {
            return 'This account is not set up for recording farm transactions.';
        }

        return $this->eligibility->reasonCannotTransact($user);
    }

    /** @return Collection<int, TransactionTemplate> numbered from 1 in the order shown */
    public function templates(string $kind, ?FarmerProfile $farmer = null): Collection
    {
        $slugs = self::SLUGS[$kind];

        return TransactionTemplate::query()->where('is_active', true)->whereIn('slug', $slugs)->get()
            // a crop farmer is not offered animal sales: the same rule the web applies
            ->filter(fn(TransactionTemplate $template) => $farmer === null || TransactionTemplate::refusalFor($template->id, $farmer) === null)
            ->sortBy(fn(TransactionTemplate $template) => array_search($template->slug, $slugs, true))
            ->values()
            ->mapWithKeys(fn(TransactionTemplate $template, int $index) => [(string) ($index + 1) => $template]);
    }

    public function find(string $slug): TransactionTemplate
    {
        return TransactionTemplate::where('slug', $slug)->firstOrFail();
    }

    // Credit (not paid yet) is for sales only
    public function isMethod(string $choice, bool $allowsCredit = false): bool
    {
        return isset(self::METHODS[$choice]) || ($allowsCredit && $choice === self::CREDIT);
    }

    /** @return array<int, string> the farmer's approved, active farm units: id => name, in the order shown */
    public function units(User $user): array
    {
        return $user->farmerProfile->farmUnits()->where('is_active', true)->whereNotNull('approved_at')
            ->orderBy('name')->limit(self::UNITS_SHOWN)->pluck('name', 'id')->all();
    }

    // how many were sold: above zero, two decimals at most, not past a million; null when it is not one
    public function quantity(string $input): ?string
    {
        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/', $input) !== 1 || (float) $input <= 0) {
            return null;
        }

        return $input;
    }

    // cedis typed as 50 or 50.5, above zero and not past the cap; null when it is not one
    public function amount(string $input): ?string
    {
        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/', $input) !== 1 || (float) $input <= 0 || (float) $input > self::MAX_CEDIS) {
            return null;
        }

        return number_format((float) $input, 2, '.', '');
    }

    public function save(User $user, TransactionTemplate $template, string $amount, string $method, string $session, ?int $unitId = null, ?string $quantity = null): Transaction
    {
        $request = new PostingRequest(
            farmerProfileId: $user->farmerProfile->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: $method === self::CREDIT ? $template->creditSettlementAccountId() : LedgerAccount::where('name', self::METHODS[$method])->value('id'),
            transactionDate: today()->toDateString(),
            farmUnitId: $unitId,
            channel: 'ussd',
            recordedBy: $user->id,
            idempotencyKey: substr('ussd.' . sha1("{$session}|{$template->slug}|{$amount}|{$method}|{$unitId}|{$quantity}"), 0, 64),
            quantitySold: $quantity,
        );

        return $this->lock->around($user->id, $request->idempotencyKey, fn() => $this->posting->post($request));
    }
}
