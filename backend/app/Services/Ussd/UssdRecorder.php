<?php

namespace App\Services\Ussd;

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

// money in or out from a phone: the three record types that need no farm unit and no quantity, posted the way the web posts them
class UssdRecorder
{
    private const SLUGS = ['income' => ['other_income'], 'expense' => ['labour_cost', 'transport_cost']];
    private const METHODS = ['1' => 'Cash A/C', '2' => 'Momo A/C', '3' => 'Bank A/C'];
    private const MAX_CEDIS = 100000;

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
    public function templates(string $kind): Collection
    {
        $slugs = self::SLUGS[$kind];

        return TransactionTemplate::query()->where('is_active', true)->whereIn('slug', $slugs)->get()
            ->sortBy(fn(TransactionTemplate $template) => array_search($template->slug, $slugs, true))
            ->values()
            ->mapWithKeys(fn(TransactionTemplate $template, int $index) => [(string) ($index + 1) => $template]);
    }

    public function isMethod(string $choice): bool
    {
        return isset(self::METHODS[$choice]);
    }

    // cedis typed as 50 or 50.5, above zero and not past the cap; null when it is not one
    public function amount(string $input): ?string
    {
        if (preg_match('/^\d{1,6}(\.\d{1,2})?$/', $input) !== 1 || (float) $input <= 0 || (float) $input > self::MAX_CEDIS) {
            return null;
        }

        return number_format((float) $input, 2, '.', '');
    }

    public function save(User $user, TransactionTemplate $template, string $amount, string $method, string $session): Transaction
    {
        $request = new PostingRequest(
            farmerProfileId: $user->farmerProfile->id,
            transactionTemplateId: $template->id,
            amount: $amount,
            settlementAccountId: LedgerAccount::where('name', self::METHODS[$method])->value('id'),
            transactionDate: today()->toDateString(),
            channel: 'ussd',
            recordedBy: $user->id,
            idempotencyKey: substr('ussd.' . sha1("{$session}|{$template->slug}|{$amount}|{$method}"), 0, 64),
        );

        return $this->lock->around($user->id, $request->idempotencyKey, fn() => $this->posting->post($request));
    }
}
