<?php

namespace App\Services\Ledger;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\CreditSettlement;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Support\Money;

class CreditSettlementService
{
    public function __construct(private readonly PostingService $posting) {}

    public function settle(
        Transaction $original,
        int $amountMinor,
        int $settlementAccountId,
        string $transactionDate,
        int $recordedBy,
        ?string $narration = null,
    ): Transaction {
        if ($amountMinor <= 0) {
            throw PostingFailed::because('The amount needs to be more than zero.');
        }

        if (! $this->isCreditTransaction($original)) {
            throw PostingFailed::because('That record was not made on credit.');
        }

        if ($amountMinor > $this->outstandingAmount($original)) {
            throw PostingFailed::because('That is more than is still owed on this record.');
        }

        $template = $this->settlementTemplate($original);

        $settlementTransaction = $this->posting->post(new PostingRequest(
            farmerProfileId: $original->farmer_profile_id,
            transactionTemplateId: $template->id,
            amount: Money::toDecimal($amountMinor),
            settlementAccountId: $settlementAccountId,
            transactionDate: $transactionDate,
            narration: $narration ?? "Settles {$original->reference}",
            recordedBy: $recordedBy,
        ));

        CreditSettlement::create([
            'transaction_id' => $original->id,
            'settlement_transaction_id' => $settlementTransaction->id,
            'amount_minor' => $amountMinor,
        ]);

        return $settlementTransaction;
    }

    // zero for a transaction that was never on credit in the first place - there is
    // nothing outstanding to settle, not an error condition for a caller to handle
    public function outstandingAmount(Transaction $original): int
    {
        if (! $this->isCreditTransaction($original)) {
            return 0;
        }

        $settled = (int) CreditSettlement::where('transaction_id', $original->id)->sum('amount_minor');

        return $original->amount_minor - $settled;
    }

    // a credit transaction is one whose template allows it AND was actually settled
    // against Receivable/Payable - allowing credit is not the same as using it
    private function isCreditTransaction(Transaction $original): bool
    {
        if (! $original->template?->allows_credit) {
            return false;
        }

        return in_array($original->settlement_account_id, LedgerAccount::creditSettlementAccountIds(), true);
    }

    private function settlementTemplate(Transaction $original): TransactionTemplate
    {
        $receivableId = LedgerAccount::where('name', 'Accounts Receivable')->value('id');

        $slug = (int) $original->settlement_account_id === (int) $receivableId
            ? 'payment_received'
            : 'payment_made';

        $template = TransactionTemplate::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if ($template === null) {
            throw PostingFailed::because('No settlement type has been set up yet.');
        }

        return $template;
    }
}
