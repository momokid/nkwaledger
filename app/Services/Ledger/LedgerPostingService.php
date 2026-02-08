<?php

namespace App\Services\Ledger;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LedgerPostingService
{
    public function __construct(
        private PeriodLockService $periodLockService
    ) {}

    public function post(LedgerTransaction $transaction): void
    {
        // 🔒 HARD ACCOUNTING RULE
        if ($this->periodLockService->isDateLocked($transaction->transaction_date)) {
            throw new \DomainException(
                'This accounting period is locked. Posting is not allowed.'
            );
        }

        DB::transaction(function () use ($transaction) {

            // 1. Approval logic
            if ($this->isSameDay($transaction)) {
                $transaction->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                ]);
            }

            // If not approved, do NOT post entries
            if ($transaction->status !== 'approved') {
                return;
            }

            // 2. Resolve accounts
            $accounts = $this->resolveAccounts($transaction->type, $transaction->is_reversalis_reversal);

            // 3. Create balanced entries
            foreach ($accounts as $entry) {
                DB::table('ledger_entries')->insert([
                    'ledger_transaction_id' => $transaction->id,
                    'account_id'            => $entry['account_id'],
                    'entry_type'            => $entry['type'],
                    'amount'                => $transaction->amount,
                    'created_at'            => now(),
                    'updated_at'            => now(),
                ]);
            }
        });
    }

    private function isSameDay(LedgerTransaction $transaction): bool
    {
        return Carbon::parse($transaction->transaction_date)
            ->isSameDay(now());
    }

    private function resolveAccounts(string $type, bool $isReversal = false): array
    {
        $entries =  match ($type) {
            'income' => [
                ['account_id' => $this->id('CASH'), 'type' => 'debit'],
                ['account_id' => $this->id('FARM_INCOME'), 'type' => 'credit'],
            ],

            'expense' => [
                ['account_id' => $this->id('FARM_EXPENSE'), 'type' => 'debit'],
                ['account_id' => $this->id('CASH'), 'type' => 'credit'],
            ],

            'loss' => [
                ['account_id' => $this->id('FARM_LOSS'), 'type' => 'debit'],
                ['account_id' => $this->id('CASH'), 'type' => 'credit'],
            ],

            default => throw new \DomainException('Unsupported transaction type'),
        };

        //Flip entries for revereal
        if ($isReversal) {
            return collect($entries)->map(fn($e) => [
                'account_id' => $e['account_id'],
                'type' => $e['type'] === 'debit' ? 'credit' : 'debit',
            ])->toArray();
        }

        return $entries;
    }



    private function id(string $code): int
    {
        return DB::table('accounts')->where('code', $code)->value('id');
    }
}
