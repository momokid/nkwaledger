<?php

namespace App\Services\Ledger;

use App\Models\CreditReminder;
use App\Models\Transaction;
use App\Services\NotificationService;
use App\Support\Money;

class CreditReminderService
{
    // three days grace after the purchase itself, then three more between each nudge
    private const REMIND_AFTER_DAYS = 3;

    public function __construct(
        private readonly CreditSettlementService $settlements,
        private readonly NotificationService $notifications,
    ) {}

    // reminds every farmer with a credit sale/purchase still outstanding three or more
    // days after it was recorded (or after the last reminder) - returns how many went out
    public function sendDue(): int
    {
        $sent = 0;

        Transaction::query()
            ->where('is_credit', true)
            ->whereDate('transaction_date', '<=', now()->subDays(self::REMIND_AFTER_DAYS)->toDateString())
            ->with(['template', 'farmerProfile.user'])
            ->chunkById(200, function ($transactions) use (&$sent) {
                foreach ($transactions as $transaction) {
                    if ($this->remind($transaction)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function remind(Transaction $transaction): bool
    {
        if ($this->settlements->outstandingAmount($transaction) <= 0) {
            return false;
        }

        if (! $this->isDue($transaction)) {
            return false;
        }

        $user = $transaction->farmerProfile?->user;

        if ($user === null) {
            return false;
        }

        $this->notifications->send($user, 'credit.reminder', $this->messageFor($transaction));

        CreditReminder::create(['transaction_id' => $transaction->id]);

        return true;
    }

    // never reminded yet and old enough, or reminded before but not for a few days
    private function isDue(Transaction $transaction): bool
    {
        $lastReminder = CreditReminder::where('transaction_id', $transaction->id)
            ->latest('id')
            ->first();

        $since = $lastReminder?->created_at ?? $transaction->transaction_date;

        return $since->lte(now()->subDays(self::REMIND_AFTER_DAYS));
    }

    private function messageFor(Transaction $transaction): string
    {
        $amount = Money::format($this->settlements->outstandingAmount($transaction));
        $name = $transaction->template?->name ?? 'a record';
        $date = $transaction->transaction_date->format('d M Y');

        return "You still owe {$amount} for {$name}, recorded on {$date}.";
    }
}
