<?php

namespace App\Services\Admin;

use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\JournalLine;
use App\Models\Region;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Agent\FarmerRosterService;
use Illuminate\Support\Carbon;

class AdminAnalyticsService
{
    private const SORTS = ['net', 'activity'];

    public function __construct(private readonly FarmerRosterService $roster) {}

    public function regionalTrends(string $from, string $to): array
    {
        $farmerIds = FarmerProfile::query()
            ->whereHas('community')
            ->pluck('id', 'id');

        $farmerRegions = FarmerProfile::query()
            ->whereIn('id', $farmerIds)
            ->with('community.district.region')
            ->get()
            ->filter(fn($farmer) => $farmer->community?->district?->region !== null)
            ->groupBy(fn($farmer) => $farmer->community->district->region->id);

        $incomeByFarmer = Transaction::query()
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('transaction_type', 'INCOME')
            ->selectRaw('farmer_profile_id, SUM(amount_minor) as total')
            ->groupBy('farmer_profile_id')
            ->pluck('total', 'farmer_profile_id');

        $expenseByFarmer = Transaction::query()
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->where('transaction_type', 'EXPENSE')
            ->selectRaw('farmer_profile_id, SUM(amount_minor) as total')
            ->groupBy('farmer_profile_id')
            ->pluck('total', 'farmer_profile_id');

        $farmUnitCounts = FarmUnit::query()
            ->selectRaw('farmer_profile_id, COUNT(*) as total')
            ->groupBy('farmer_profile_id')
            ->pluck('total', 'farmer_profile_id');

        return $farmerRegions->map(function ($farmers, $regionId) use ($incomeByFarmer, $expenseByFarmer, $farmUnitCounts) {
            $income = $farmers->sum(fn($farmer) => $incomeByFarmer[$farmer->id] ?? 0);
            $expense = $farmers->sum(fn($farmer) => $expenseByFarmer[$farmer->id] ?? 0);

            return [
                'region_id' => (int) $regionId,
                'region_name' => Region::find($regionId)?->name ?? '',
                'farmer_count' => $farmers->count(),
                'farm_unit_count' => $farmers->sum(fn($farmer) => $farmUnitCounts[$farmer->id] ?? 0),
                'income' => (int) $income,
                'expense' => (int) $expense,
                'net' => (int) ($income - $expense),
            ];
        })->values()->all();
    }

    public function healthTrends(string $from, string $to): array
    {
        $reports = DiseaseReport::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->with('farmerProfile.community.district.region')
            ->get()
            ->filter(fn($report) => $report->farmerProfile?->community?->district?->region !== null)
            ->groupBy(fn($report) => $report->farmerProfile->community->district->region->id);

        return $reports->map(function ($regionReports, $regionId) {
            return [
                'region_id' => (int) $regionId,
                'region_name' => Region::find($regionId)?->name ?? '',
                'total' => $regionReports->count(),
                'by_category' => $regionReports->countBy('category')->all(),
                'by_status' => $regionReports->countBy(fn($report) => $report->status->value)->all(),
            ];
        })->values()->all();
    }

    public function platformSnapshot(string $from, string $to): array
    {
        // "active" is always the trailing 30 days, independent of whatever range the
        // rest of the snapshot is filtered to
        $activityFrom = Carbon::now()->subDays(29)->toDateString();
        $activityTo = Carbon::now()->toDateString();

        $activeFarmerIds = Transaction::query()
            ->whereDate('transaction_date', '>=', $activityFrom)
            ->whereDate('transaction_date', '<=', $activityTo)
            ->distinct()
            ->pluck('farmer_profile_id');

        $activeAgents = FarmerProfile::query()
            ->whereIn('id', $activeFarmerIds)
            ->whereNotNull('assigned_agent_id')
            ->distinct()
            ->count('assigned_agent_id');

        [$income, $expense] = $this->platformTotals($from, $to);

        return [
            'active_farmers' => $activeFarmerIds->count(),
            'active_agents' => $activeAgents,
            'total_farmers' => FarmerProfile::count(),
            'total_agents' => User::role('agent')->count(),
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $income - $expense,
        ];
    }

    public function agentLeaderboard(string $from, string $to, string $sort = 'net'): array
    {
        $sort = in_array($sort, self::SORTS, true) ? $sort : 'net';

        $agents = User::role('agent')->orderBy('surname')->orderBy('first_name')->get(['id', 'surname', 'first_name']);

        $farmerCounts = $this->countsByAgent(FarmerProfile::query()->whereNotNull('assigned_agent_id'));

        $newFarmerCounts = $this->countsByAgent(
            FarmerProfile::query()
                ->whereNotNull('assigned_agent_id')
                ->whereDate('onboarded_at', '>=', $from)
                ->whereDate('onboarded_at', '<=', $to)
        );

        $recordCounts = Transaction::query()
            ->join('farmer_profiles', 'farmer_profiles.id', '=', 'transactions.farmer_profile_id')
            ->whereNotNull('farmer_profiles.assigned_agent_id')
            ->whereDate('transactions.transaction_date', '>=', $from)
            ->whereDate('transactions.transaction_date', '<=', $to)
            ->selectRaw('farmer_profiles.assigned_agent_id as agent_id, COUNT(*) as total')
            ->groupBy('farmer_profiles.assigned_agent_id')
            ->pluck('total', 'agent_id');

        $rows = $agents->map(function (User $agent) use ($from, $to, $farmerCounts, $newFarmerCounts, $recordCounts) {
            [$income, $expense] = $this->roster->totalsFor($agent->id, $from, $to);

            return [
                'agent_id' => $agent->id,
                'name' => trim("{$agent->surname} {$agent->first_name}"),
                'farmer_count' => (int) ($farmerCounts[$agent->id] ?? 0),
                'new_farmers' => (int) ($newFarmerCounts[$agent->id] ?? 0),
                'records_logged' => (int) ($recordCounts[$agent->id] ?? 0),
                'income' => $income,
                'expense' => $expense,
                'net' => $income - $expense,
            ];
        })->all();

        return $this->ranked($rows, $sort);
    }

    // the platform-wide equivalent of IncomeAndExpenditureService's private totals()
    // query, minus the per-account breakdown this doesn't need
    private function platformTotals(string $from, string $to): array
    {
        $totals = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('transactions', 'transactions.id', '=', 'journal_entries.transaction_id')
            ->join('ledger_accounts', 'ledger_accounts.id', '=', 'journal_lines.ledger_account_id')
            ->whereDate('journal_lines.transaction_date', '>=', $from)
            ->whereDate('journal_lines.transaction_date', '<=', $to)
            ->whereIn('transactions.transaction_type', [Transaction::INCOME, Transaction::EXPENSE])
            ->where('ledger_accounts.is_settlement', false)
            ->where('transactions.is_provisional', false)
            ->where(fn($query) => $query
                ->where(fn($income) => $income
                    ->where('transactions.transaction_type', Transaction::INCOME)
                    ->where('journal_lines.credit_minor', '>', 0))
                ->orWhere(fn($outgoing) => $outgoing
                    ->where('transactions.transaction_type', Transaction::EXPENSE)
                    ->where('journal_lines.debit_minor', '>', 0)))
            ->select('transactions.transaction_type')
            ->selectRaw('SUM(journal_lines.debit_minor + journal_lines.credit_minor) as amount_minor')
            ->groupBy('transactions.transaction_type')
            ->get()
            ->pluck('amount_minor', 'transaction_type');

        return [
            (int) ($totals[Transaction::INCOME] ?? 0),
            (int) ($totals[Transaction::EXPENSE] ?? 0),
        ];
    }

    private function countsByAgent($query)
    {
        return $query
            ->selectRaw('assigned_agent_id, COUNT(*) as total')
            ->groupBy('assigned_agent_id')
            ->pluck('total', 'assigned_agent_id');
    }

    // highest first; every row carries both metrics regardless of which one is active,
    // so switching sort client-side never needs a fresh request
    private function ranked(array $rows, string $sort): array
    {
        usort($rows, function ($a, $b) use ($sort) {
            $valueA = $sort === 'activity' ? $a['records_logged'] : $a['net'];
            $valueB = $sort === 'activity' ? $b['records_logged'] : $b['net'];

            return $valueB <=> $valueA;
        });

        return array_values(array_map(
            fn($row, $index) => [...$row, 'rank' => $index + 1],
            $rows,
            array_keys($rows),
        ));
    }
}
