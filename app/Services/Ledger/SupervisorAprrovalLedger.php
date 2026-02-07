<?php

namespace App\Services\Ledger;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\DB;

class SupervisorApprovalService
{
    public function __construct(
        private LedgerPostingService $postingService
    ) {}

    public function approve(
        LedgerTransaction $transaction,
        int $supervisorId
    ): void {

        if ($transaction->status !== 'pending') {
            throw new \DomainException('Transaction already processed');
        }

        DB::transaction(function () use ($transaction, $supervisorId) {

            $transaction->update([
                'status'      => 'approved',
                'approved_by' => $supervisorId,
                'approved_at' => now(),
            ]);

            // Post to ledger now
            $this->postingService->post($transaction);
        });
    }

    public function reject(
        LedgerTransaction $transaction,
        int $supervisorId,
        string $reason
    ): void {

        if ($transaction->status !== 'pending') {
            throw new \DomainException('Transaction already processed');
        }

        $transaction->update([
            'status'      => 'rejected',
            'approved_by' => $supervisorId,
            'approved_at' => now(),
        ]);
    }
}
