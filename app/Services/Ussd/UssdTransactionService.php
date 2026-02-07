<?php

namespace App\Services\Ussd;

use App\Models\User;
use App\Models\LedgerTransaction;
use App\Services\Ledger\LedgerPostingService;
use App\Services\Ledger\PeriodLockService;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class UssdTransactionService
{
    public function __construct(
        private LedgerPostingService $postingService,
        private PeriodLockService $periodLockService
    ) {}

    public function postTransaction(
        int $userId,
        string $type,
        float $amount,
        string $password,
        string $transactionDate
    ): LedgerTransaction {

        $user = User::findOrFail($userId);

        // 1️ Confirm password
        if (!Hash::check($password, $user->password)) {
            throw new \DomainException('Invalid password');
        }

        // 2️ Create transaction
        $transaction = LedgerTransaction::create([
            'user_id'          => $user->id,
            'type'             => $type,
            'amount'           => $amount,
            'transaction_date' => Carbon::parse($transactionDate),
            'status'           => 'pending',
        ]);

        // 3️ Post to ledger (auto-approval handled inside)
        $this->postingService->post($transaction);

        //period lock check
        if ($this->periodLockService->isDateLocked($transactionDate)) {
            throw new \DomainException('Transactions for this date are locked');
        }

        return $transaction;
    }
}
