<?php

namespace App\Services\Ledger\Reports;

use App\Models\FarmerProfile;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use App\Models\Transaction;
use App\Services\Ledger\CreditSettlementService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

class IncomeAndExpenditureService
{
    public function __construct(private readonly CreditSettlementService $creditSettlements) {}

    public function for(
        int $farmerProfileId,
        string $from,
        string $to,
        bool $includeProvisional = false,
    ): IncomeAndExpenditure {
        $totals = $this->totals($farmerProfileId, $from, $to, $includeProvisional);

        $accounts = LedgerAccount::query()
            // the group name is two levels down, so it loads once instead of per row
            ->with('subcategory')
            ->whereIn('id', $totals->pluck('ledger_account_id')->unique())
            ->orderBy('name')
            ->get()
            ->keyBy('id');

        $incomeRows = $this->rows($totals, $accounts, Transaction::INCOME);
        $expenseRows = $this->rows($totals, $accounts, Transaction::EXPENSE);
        $lossRows = $this->rows($totals, $accounts, Transaction::LOSS);

        $profile = FarmerProfile::query()->with('user')->findOrFail($farmerProfileId);

        return new IncomeAndExpenditure(
            farmerProfileId: $farmerProfileId,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
            provisionalHeldBackMinor: $includeProvisional
                ? 0
                : $this->heldBack($farmerProfileId, $from, $to),
            generatedAt: now(),
            incomeRows: $incomeRows,
            expenseRows: $expenseRows,
            lossRows: $lossRows,
            header: ReportHeader::make(
                title: 'Income and Expenditure',
                farmerProfile: $profile,
                from: $from,
                to: $to,
                includeProvisional: $includeProvisional,
                // the three sections sign apart, so a loss never looks like an expense
                figures: [
                    'income' => $this->sum($incomeRows),
                    'expense' => $this->sum($expenseRows),
                    'loss' => $this->sum($lossRows),
                ],
            ),
            cashCollectedMinor: $this->cashFlow($farmerProfileId, $from, $to, $includeProvisional, Transaction::INCOME),
            cashPaidOutMinor: $this->cashFlow($farmerProfileId, $from, $to, $includeProvisional, Transaction::EXPENSE),
            assetsAcquiredMinor: $this->assetsAcquired($farmerProfileId, $from, $to, $includeProvisional),
        );
    }

    // real cash left the farmer's hand either way, but buying stock is an asset gained,
    // not money spent running the farm - tracked here on its own, counted in full
    // regardless of whether it was settled in cash or still sits on credit
    private function assetsAcquired(int $farmerProfileId, string $from, string $to, bool $includeProvisional): int
    {
        return (int) Transaction::query()
            ->join('transaction_templates', 'transaction_templates.id', '=', 'transactions.transaction_template_id')
            ->where('transactions.farmer_profile_id', $farmerProfileId)
            ->where('transaction_templates.is_stock_purchase', true)
            ->whereDate('transactions.transaction_date', '>=', $from)
            ->whereDate('transactions.transaction_date', '<=', $to)
            ->when(! $includeProvisional, fn($query) => $query->where('transactions.is_provisional', false))
            ->sum('transactions.amount_minor');
    }

    // the cash/MoMo portion actually received or paid: every non-credit transaction
    // in full, plus whatever has actually been settled so far on a credit one -
    // "so far" reaches past the period's own end date on purpose, since a credit
    // sale from this period settled next month should stop showing as uncollected
    private function cashFlow(int $farmerProfileId, string $from, string $to, bool $includeProvisional, string $type): int
    {
        return (int) Transaction::query()
            ->where('farmer_profile_id', $farmerProfileId)
            ->where('transaction_type', $type)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->when(! $includeProvisional, fn($query) => $query->where('is_provisional', false))
            // outstandingAmount() needs the template relation and settlement account,
            // so this reads full rows rather than a narrow column list
            ->get()
            ->sum(function (Transaction $transaction) {
                if (! $transaction->is_credit) {
                    return $transaction->amount_minor;
                }

                return $transaction->amount_minor - $this->creditSettlements->outstandingAmount($transaction);
            });
    }

    /** @param array<int, IncomeLine> $rows */
    private function sum(array $rows): int
    {
        return array_sum(array_map(fn(IncomeLine $row) => $row->amountMinor, $rows));
    }

    /** @return array<int, IncomeLine> */
    private function rows(Collection $totals, Collection $accounts, string $type): array
    {
        return $totals
            ->where('transaction_type', $type)
            ->map(function ($total) use ($accounts) {
                $account = $accounts[$total->ledger_account_id];

                return new IncomeLine(
                    accountId: (int) $account->id,
                    accountName: $account->name,
                    accountCode: $account->account_code,
                    groupName: $account->subcategory?->name ?? '',
                    amountMinor: (int) $total->amount_minor,
                );
            })
            ->sortBy('accountName')
            ->values()
            ->all();
    }

    // income sits on the credit side, expenses and losses on the debit side
    private function totals(int $farmerProfileId, string $from, string $to, bool $includeProvisional): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->join('transaction_templates', 'transaction_templates.id', '=', 'transactions.transaction_template_id')
            ->where('journal_lines.farmer_profile_id', $farmerProfileId)
            ->whereDate('journal_lines.transaction_date', '>=', $from)
            ->whereDate('journal_lines.transaction_date', '<=', $to)
            ->whereIn('transactions.transaction_type', [
                Transaction::INCOME,
                Transaction::EXPENSE,
                Transaction::LOSS,
            ])
            // the money the farmer holds is not earnings or spending, it is where it sits
            ->where('ledger_accounts.is_settlement', false)
            // an asset acquired distorts operating profit - pulled out of the expense total
            // here and surfaced on its own via assetsAcquired() instead
            ->where(fn($query) => $query
                ->whereNot('transactions.transaction_type', Transaction::EXPENSE)
                ->orWhere('transaction_templates.is_stock_purchase', false))
            ->when(! $includeProvisional, fn($query) => $query->where('transactions.is_provisional', false))
            // earning sits on the credit side, paying out and value gone sit on the debit side
            ->where(fn($query) => $query
                ->where(fn($income) => $income
                    ->where('transactions.transaction_type', Transaction::INCOME)
                    ->where('journal_lines.credit_minor', '>', 0))
                ->orWhere(fn($outgoing) => $outgoing
                    ->whereIn('transactions.transaction_type', [Transaction::EXPENSE, Transaction::LOSS])
                    ->where('journal_lines.debit_minor', '>', 0)))
            ->select('journal_lines.ledger_account_id', 'transactions.transaction_type')
            ->selectRaw('SUM(journal_lines.debit_minor + journal_lines.credit_minor) as amount_minor')
            ->groupBy('journal_lines.ledger_account_id', 'transactions.transaction_type')
            ->get();
    }

    private function heldBack(int $farmerProfileId, string $from, string $to): int
    {
        return (int) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->where('journal_lines.farmer_profile_id', $farmerProfileId)
            ->whereDate('journal_lines.transaction_date', '>=', $from)
            ->whereDate('journal_lines.transaction_date', '<=', $to)
            ->where('transactions.is_provisional', true)
            ->where('ledger_accounts.is_settlement', false)
            ->where(fn($query) => $query
                ->where(fn($income) => $income
                    ->where('transactions.transaction_type', Transaction::INCOME)
                    ->where('journal_lines.credit_minor', '>', 0))
                ->orWhere(fn($outgoing) => $outgoing
                    ->whereIn('transactions.transaction_type', [Transaction::EXPENSE, Transaction::LOSS])
                    ->where('journal_lines.debit_minor', '>', 0)))
            ->sum(JournalLine::raw('journal_lines.debit_minor + journal_lines.credit_minor'));
    }
}
