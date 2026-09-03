<?php

namespace App\Services\Ledger\Reports;

use App\Models\FarmerProfile;
use App\Models\JournalLine;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\DB;

class TrialBalanceService
{
    public function for(
        int $farmerProfileId,
        string $from,
        string $to,
        bool $includeProvisional = false,
    ): TrialBalance {
        $totals = $this->totalsByAccount($farmerProfileId, $from, $to, $includeProvisional);

        $accounts = LedgerAccount::query()
            // the Dr or Cr walk is three levels deep, so it loads once instead of per row
            ->with('subcategory.category.class')
            ->whereIn('id', $totals->keys())
            ->orderBy('name')
            ->get();

        $rows = $accounts->map(fn(LedgerAccount $account) => new TrialBalanceRow(
            accountId: $account->id,
            accountName: $account->name,
            accountCode: $account->account_code,
            class: $account->class,
            debitMinor: (int) $totals[$account->id]->debit_minor,
            creditMinor: (int) $totals[$account->id]->credit_minor,
        ))->values()->all();

        $profile = FarmerProfile::query()->with('user')->findOrFail($farmerProfileId);

        return new TrialBalance(
            farmerProfileId: $farmerProfileId,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
            provisionalHeldBackMinor: $includeProvisional
                ? 0
                : $this->provisionalHeldBack($farmerProfileId, $from, $to),
            generatedAt: now(),
            rows: $rows,
            header: ReportHeader::make(
                title: 'Trial Balance',
                farmerProfile: $profile,
                from: $from,
                to: $to,
                includeProvisional: $includeProvisional,
                // the two totals the whole report rests on
                figures: [
                    'debit' => array_sum(array_map(fn($row) => $row->debitMinor, $rows)),
                    'credit' => array_sum(array_map(fn($row) => $row->creditMinor, $rows)),
                ],
            ),
        );
    }

    // one grouped read of journal_lines, covered by the farmer and date index
    private function totalsByAccount(
        int $farmerProfileId,
        string $from,
        string $to,
        bool $includeProvisional,
    ) {
        return $this->scope($farmerProfileId, $from, $to, $includeProvisional)
            ->select('journal_lines.ledger_account_id')
            ->selectRaw('SUM(journal_lines.debit_minor) as debit_minor')
            ->selectRaw('SUM(journal_lines.credit_minor) as credit_minor')
            ->groupBy('journal_lines.ledger_account_id')
            ->get()
            ->keyBy('ledger_account_id');
    }

    // what the farmer wrote but the books cannot yet count
    private function provisionalHeldBack(int $farmerProfileId, string $from, string $to): int
    {
        return (int) JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->where('journal_lines.farmer_profile_id', $farmerProfileId)
            ->whereDate('journal_lines.transaction_date', '>=', $from)
            ->whereDate('journal_lines.transaction_date', '<=', $to)
            ->where('transactions.is_provisional', true)
            ->sum('journal_lines.debit_minor');
    }

    private function scope(int $farmerProfileId, string $from, string $to, bool $includeProvisional)
    {
        $query = JournalLine::query()
            ->where('journal_lines.farmer_profile_id', $farmerProfileId)
            ->whereDate('journal_lines.transaction_date', '>=', $from)
            ->whereDate('journal_lines.transaction_date', '<=', $to);

        if ($includeProvisional) {
            return $query;
        }

        // both legs leave together, or dropping one would tip the books over
        return $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('journal_entries')
                ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
                ->whereColumn('journal_entries.id', 'journal_lines.journal_entry_id')
                ->where('transactions.is_provisional', true);
        });
    }
}
