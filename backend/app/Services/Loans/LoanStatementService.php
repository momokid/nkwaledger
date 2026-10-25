<?php

namespace App\Services\Loans;

use App\Models\Loan;
use App\Models\Transaction;

// Everything the loan statement shows, for one loan: the loan, its repayments and what the money was used for.
// Read from the books whenever it is asked for; nothing here is stored. Amounts are in pesewas, dates are YYYY-MM-DD.
class LoanStatementService
{
    public function __construct(private readonly LoanListService $list) {}

    /** @return array<string, mixed> */
    public function for(Loan $loan): array
    {
        $loan->loadMissing(['lender', 'settlementAccount']);

        // only the purchases the farmer marked "Paid from a loan", and only those still standing
        $purchases = Transaction::query()
            ->live()
            ->where('loan_id', $loan->id)
            ->with(['template:id,name', 'farmUnit:id,name'])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get()
            ->map(fn(Transaction $record) => [
                'date' => $record->transaction_date->toDateString(),
                'what' => $record->template?->name ?? '',
                'farm' => $record->farmUnit?->name,
                'amount' => $record->amount_minor,
            ])
            ->values();

        $otherUses = $loan->otherUses()->orderBy('id')->get()->map(fn($use) => ['uuid' => $use->uuid, 'note' => $use->note, 'amount' => $use->amount_minor])->values();

        return [
            'loan' => [
                'uuid' => $loan->uuid,
                'lender' => $loan->lender_name,
                'amount' => $loan->principal_minor,
                'granted_on' => $loan->loan_date?->toDateString(),
                'paid_into' => LoanService::PAID_INTO_LABELS[$loan->settlementAccount?->name] ?? null,
                'interest' => $loan->agreed_interest_minor,
                'due_date' => $loan->due_date?->toDateString(),
                'status' => $loan->displayStatus(),
                'balance' => $loan->balanceMinor(),
            ],
            'repayments' => $this->list->repaymentRows($loan)->all(),
            'farms' => $loan->farmFigures()->all(),
            'purchases' => $purchases->all(),
            'kinds' => $purchases->groupBy('what')->map(fn($rows, $name) => ['name' => $name, 'amount' => $rows->sum('amount')])->values()->all(),
            'other_uses' => $otherUses->all(),
            'totals' => [
                'on_farms' => $purchases->sum('amount'),
                'other' => $otherUses->sum('amount'),
                'not_yet' => $loan->unaccountedMinor(),
            ],
            'prepared_on' => today()->toDateString(),
            'notice' => Loan::NOTICE,
        ];
    }
}
