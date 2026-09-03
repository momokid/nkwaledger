<?php

namespace App\Services\Ledger\Reports;

use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\JournalLine;

class AccountStatementService
{
    public function for(
        int $farmerProfileId,
        string $from,
        string $to,
        bool $includeProvisional = false,
        ?int $accountId = null,
        int $page = 1,
        int $perPage = 50,
    ): AccountStatement {
        $settlementAccounts = $this->settlementAccounts($accountId);

        $base = $this->scope($farmerProfileId, $includeProvisional, $accountId);

        $total = (clone $base)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->count();

        // everything before this page, so the balance never restarts halfway down
        $opening = $this->balanceBefore($farmerProfileId, $from, $to, $includeProvisional, $accountId, $page, $perPage, $settlementAccounts);

        $transactions = (clone $base)
            ->with(['template:id,name', 'settlementAccount:id,name'])
            ->withExists('reversedBy as is_cancelled')
            ->withExists(['reversalRequests as has_pending_cancel' => fn($query) => $query->where('status', 'pending')])
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            // two records on one day need a tiebreak, or pages drift between reads
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        $rows = $this->rows($transactions, $opening, $settlementAccounts);

        $profile = FarmerProfile::query()->with('user')->findOrFail($farmerProfileId);

        return new AccountStatement(
            farmerProfileId: $farmerProfileId,
            from: $from,
            to: $to,
            accountId: $accountId,
            includeProvisional: $includeProvisional,
            provisionalHeldBackMinor: $this->heldBack($farmerProfileId, $from, $to, $accountId, $settlementAccounts),
            openingBalanceMinor: $opening,
            generatedAt: now(),
            rows: $rows,
            total: $total,
            page: $page,
            perPage: $perPage,
            header: ReportHeader::make(
                title: 'Account Statement',
                farmerProfile: $profile,
                from: $from,
                to: $to,
                includeProvisional: $includeProvisional,
                // the page number is in here, so two pages never sign the same
                figures: [
                    'opening' => $opening,
                    'in' => array_sum(array_map(fn($row) => $row->moneyInMinor, $rows)),
                    'out' => array_sum(array_map(fn($row) => $row->moneyOutMinor, $rows)),
                    'closing' => $rows === [] ? $opening : $rows[array_key_last($rows)]->balanceMinor,
                    'page' => $page,
                ],
            ),
        );
    }

    private function cancelState(Transaction $transaction): string
    {
        if ($transaction->transaction_type === Transaction::ADJUSTMENT) {
            return 'correction';
        }

        if ($transaction->is_cancelled) {
            return 'cancelled';
        }

        if ($transaction->has_pending_cancel) {
            return 'waiting';
        }

        return 'open';
    }

    /** @return array<int, AccountStatementRow> */
    private function rows(Collection $transactions, int $opening, Collection $settlementAccounts): array
    {
        $balance = $opening;
        $rows = [];

        foreach ($transactions as $transaction) {
            $in = $this->moneyIn($transaction, $settlementAccounts);
            $out = $this->moneyOut($transaction, $settlementAccounts);

            $balance += $in - $out;

            $rows[] = new AccountStatementRow(
                transactionId: $transaction->id,
                uuid: $transaction->uuid,
                reference: $transaction->reference,
                transactionDate: $transaction->transaction_date->toDateString(),
                transactionType: $transaction->transaction_type,
                templateName: $transaction->template->name,
                description: $transaction->narration ?: $transaction->template->name,
                moneyInMinor: $in,
                moneyOutMinor: $out,
                balanceMinor: $balance,
                isProvisional: (bool) $transaction->is_provisional,
                cancelState: $this->cancelState($transaction),
                accountName: $transaction->settlementAccount?->name,
                valueLostMinor: $transaction->transaction_type === Transaction::LOSS
                    ? (int) $transaction->amount_minor
                    : 0,
            );
        }

        return $rows;
    }

    // money only moves when a settlement account was named
    private function moneyIn(Transaction $transaction, Collection $settlementAccounts): int
    {
        if (! $this->touchesCash($transaction, $settlementAccounts)) {
            return 0;
        }

        return $this->cashRises($transaction) ? (int) $transaction->amount_minor : 0;
    }

    private function moneyOut(Transaction $transaction, Collection $settlementAccounts): int
    {
        if (! $this->touchesCash($transaction, $settlementAccounts)) {
            return 0;
        }

        return $this->cashRises($transaction) ? 0 : (int) $transaction->amount_minor;
    }

    private function cashRises(Transaction $transaction): bool
    {
        if ($transaction->transaction_type !== Transaction::ADJUSTMENT) {
            return $transaction->transaction_type === Transaction::INCOME;
        }

        $line = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_entries.transaction_id', $transaction->id)
            ->where('journal_lines.ledger_account_id', $transaction->settlement_account_id)
            ->first(['journal_lines.debit_minor']);

        return $line !== null && (int) $line->debit_minor > 0;
    }

    private function touchesCash(Transaction $transaction, Collection $settlementAccounts): bool
    {
        if ($transaction->settlement_account_id === null) {
            return false;
        }

        return $settlementAccounts->contains((int) $transaction->settlement_account_id);
    }

    // what the farmer held before the first row on this page
    private function balanceBefore(
        int $farmerProfileId,
        string $from,
        string $to,
        bool $includeProvisional,
        ?int $accountId,
        int $page,
        int $perPage,
        Collection $settlementAccounts,
    ): int {
        $earlier = $this->scope($farmerProfileId, $includeProvisional, $accountId)
            ->whereDate('transaction_date', '<', $from)
            ->get();

        $balance = $this->netOf($earlier, $settlementAccounts);

        if ($page <= 1) {
            return $balance;
        }

        $skipped = $this->scope($farmerProfileId, $includeProvisional, $accountId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->limit(($page - 1) * $perPage)
            ->get();

        return $balance + $this->netOf($skipped, $settlementAccounts);
    }

    // counted whether or not the rows are shown, so the farmer always knows what is waiting
    private function heldBack(
        int $farmerProfileId,
        string $from,
        string $to,
        ?int $accountId,
        Collection $settlementAccounts,
    ): int {
        $waiting = $this->scope($farmerProfileId, true, $accountId)
            ->where('is_provisional', true)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->get();

        return $waiting->reduce(
            fn(int $carry, Transaction $transaction) => $carry
                + $this->moneyIn($transaction, $settlementAccounts)
                + $this->moneyOut($transaction, $settlementAccounts),
            0,
        );
    }

    private function netOf(Collection $transactions, Collection $settlementAccounts): int
    {
        return $transactions->reduce(
            fn(int $carry, Transaction $transaction) => $carry
                + $this->moneyIn($transaction, $settlementAccounts)
                - $this->moneyOut($transaction, $settlementAccounts),
            0,
        );
    }

    private function scope(int $farmerProfileId, bool $includeProvisional, ?int $accountId): Builder
    {
        return Transaction::query()
            ->where('farmer_profile_id', $farmerProfileId)
            ->when(! $includeProvisional, fn(Builder $query) => $query->where('is_provisional', false))
            ->when($accountId !== null, fn(Builder $query) => $query->where('settlement_account_id', $accountId));
    }

    // reads the few ticked accounts instead of scanning every transaction ever made
    private function settlementAccounts(?int $accountId): Collection
    {
        if ($accountId !== null) {
            return collect([$accountId]);
        }

        return LedgerAccount::settlement()->pluck('id')->map(fn($id) => (int) $id);
    }
}
