<?php

namespace App\Services\Loans;

use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Models\User;
use Illuminate\Support\Collection;

// what staff see on a farmer's page: the loans and the entries that have not become loans, newest first.
// An accepted entry is the loan itself, so it is never listed twice
class LoanListService
{
    /** @return Collection<int, array<string, mixed>> */
    public function forStaff(FarmerProfile $farmer): Collection
    {
        $loans = Loan::where('farmer_profile_id', $farmer->id)->with(['lender', 'currentCheck'])->get()->map(fn(Loan $loan) => [
            'at' => $loan->created_at,
            'id' => $loan->id,
            'row' => [
                'row' => 'loan',
                'uuid' => $loan->uuid,
                'lender' => $loan->lender_name,
                'amount' => $loan->principal_minor,
                'balance' => $loan->balanceMinor(),
                'status' => $loan->displayStatus(),
                'check' => LoanCheckService::present($loan->currentCheck),
            ],
        ]);

        $entries = PendingEntry::where('farmer_profile_id', $farmer->id)
            ->whereIn('status', [PendingEntry::PENDING, PendingEntry::DECLINED, PendingEntry::WITHDRAWN, PendingEntry::REJECTED])
            ->get()
            ->map(fn(PendingEntry $entry) => [
                'at' => $entry->created_at,
                'id' => $entry->id,
                'row' => [
                    'row' => 'entry',
                    'uuid' => $entry->uuid,
                    'kind' => $entry->isRepayment() ? 'repayment' : 'loan',
                    'lender' => $entry->lenderName(),
                    'amount' => $entry->payload['principal_minor'] ?? $entry->payload['amount_minor'],
                    'balance' => null,
                    'status' => $entry->status,
                ],
            ]);

        return $loans->concat($entries)->sortBy([['at', 'desc'], ['id', 'desc']])->pluck('row')->values();
    }

    // the cards at the top of the farmer's own page
    public function pendingFor(FarmerProfile $farmer): Collection
    {
        return PendingEntry::with('recordedBy:id,surname,first_name')
            ->where('farmer_profile_id', $farmer->id)
            ->where('status', PendingEntry::PENDING)
            ->orderByDesc('id')
            ->get()
            ->map(fn(PendingEntry $entry) => [
                'uuid' => $entry->uuid,
                'kind' => $entry->isRepayment() ? 'repayment' : 'loan',
                'lender' => $entry->lenderName(),
                'amount' => $entry->payload['principal_minor'] ?? $entry->payload['amount_minor'],
                // an opening entry is dated the day it is accepted, so the card shows the day it was recorded
                'date' => $entry->isRepayment()
                    ? $entry->payload['date']
                    : ($entry->payload['kind'] === Loan::KIND_MONEY ? $entry->payload['loan_date'] : $entry->created_at->toDateString()),
                'agent' => $entry->recordedBy ? trim("{$entry->recordedBy->surname} {$entry->recordedBy->first_name}") : '',
            ])
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> the repayments made on a loan, oldest first, each with what was still owed after it; public ids only */
    public function repaymentRows(Loan $loan): Collection
    {
        $owed = $loan->principal_minor + ($loan->agreed_interest_minor ?? 0);

        return $loan->repayments()
            ->with(['principalTransaction.reversedBy', 'interestTransaction.reversedBy'])
            ->orderBy('repaid_on')
            ->orderBy('id')
            ->get()
            ->map(function (\App\Models\LoanRepayment $repayment) use (&$owed) {
                $cancelled = $repayment->principalTransaction?->reversedBy !== null || $repayment->interestTransaction?->reversedBy !== null;
                $owed -= $cancelled ? 0 : $repayment->amount_minor;

                return [
                    'uuid' => $repayment->uuid,
                    'date' => $repayment->repaid_on->toDateString(),
                    'amount' => $repayment->amount_minor,
                    'principal' => $repayment->principal_minor,
                    'interest' => $repayment->interest_minor,
                    'balance' => $owed,
                    'cancelled' => $cancelled,
                ];
            })
            ->values();
    }

    // the repayments an agent entered for this loan that were rejected, so the farmer can see what happened to them
    public function rejectedRepayments(Loan $loan): Collection
    {
        return PendingEntry::where('farmer_profile_id', $loan->farmer_profile_id)
            ->where('entry_type', PendingEntry::LOAN_REPAYMENT)
            ->where('status', PendingEntry::REJECTED)
            ->orderByDesc('id')
            ->get()
            ->filter(fn(PendingEntry $entry) => $entry->payload['loan_uuid'] === $loan->uuid)
            ->map(fn(PendingEntry $entry) => ['uuid' => $entry->uuid, 'date' => $entry->payload['date'], 'amount' => $entry->payload['amount_minor']])
            ->values();
    }

    // whether this person sees the actions, not just the list
    public function canAct(User $user, FarmerProfile $farmer): bool
    {
        return $user->hasRole('agent') && (int) $farmer->assigned_agent_id === $user->id;
    }
}
