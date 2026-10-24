<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\LoanReminder;
use App\Services\NotificationService;

class LoanUseReminderService
{
    // the first nudge comes a week after the loan was recorded, then one every fortnight until it is cleared
    private const FIRST_AFTER_DAYS = 7;
    private const THEN_EVERY_DAYS = 14;

    public const MESSAGE = 'Please record how you used your loan.';

    public function __construct(private readonly NotificationService $notifications) {}

    // returns how many reminders went out
    public function sendDue(): int
    {
        $sent = 0;

        Loan::query()
            ->where('status', Loan::ACTIVE)
            ->where('kind', Loan::KIND_MONEY)
            ->whereHas('farmAllocations')
            ->with('farmerProfile.user')
            ->chunkById(200, function ($loans) use (&$sent) {
                foreach ($loans as $loan) {
                    if ($this->remind($loan)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function remind(Loan $loan): bool
    {
        $user = $loan->farmerProfile?->user;
        $last = LoanReminder::where('loan_id', $loan->id)->latest('id')->first();

        $since = $last?->created_at ?? $loan->created_at;
        $wait = $last === null ? self::FIRST_AFTER_DAYS : self::THEN_EVERY_DAYS;

        if ($user === null || $since->gt(now()->subDays($wait)) || $loan->unaccountedMinor() <= 0) {
            return false;
        }

        $this->notifications->send($user, 'loan.use_reminder', self::MESSAGE, "/loans/{$loan->uuid}");

        LoanReminder::create(['loan_id' => $loan->id]);

        return true;
    }
}
