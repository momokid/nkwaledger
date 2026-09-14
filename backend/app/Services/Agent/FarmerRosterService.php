<?php

namespace App\Services\Agent;

use App\Models\FarmerProfile;
use App\Models\Transaction;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FarmerRosterService
{
    public function __construct(
        private readonly IncomeAndExpenditureService $incomes,
    ) {}

    // walks every farmer assigned to this agent and adds up their own report; a farmer
    // counts as active only if something moved through their books in this period.
    // rows (the per-farmer roster) are only built when asked for, since the dashboard's
    // trend comparison against the prior period never needs them.
    public function totalsFor(int $agentId, string $from, string $to, bool $withRows = false): array
    {
        $farmers = FarmerProfile::query()
            ->where('assigned_agent_id', $agentId)
            ->when($withRows, fn($query) => $query->with([
                'user:id,surname,first_name',
                'community:id,name',
            ]))
            ->get();

        $lastActivity = $withRows ? $this->lastActivityFor($farmers->pluck('id')) : collect();

        $income = 0;
        $expense = 0;
        $activeCount = 0;
        $rows = [];

        foreach ($farmers as $farmer) {
            $report = $this->incomes->for(farmerProfileId: $farmer->id, from: $from, to: $to);

            $income += $report->totalIncomeMinor;
            $expense += $report->totalExpenseMinor;

            $isActive = $report->totalIncomeMinor > 0 || $report->totalExpenseMinor > 0;

            if ($isActive) {
                $activeCount++;
            }

            if ($withRows) {
                $rows[] = [
                    'id' => $farmer->uuid,
                    'farmer_profile_id' => $farmer->id,
                    'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                    'community' => $farmer->community?->name,
                    'last_activity' => $lastActivity->get($farmer->id),
                    'income' => $report->totalIncomeMinor,
                    'expense' => $report->totalExpenseMinor,
                    'status' => $isActive ? 'active' : 'dormant',
                ];
            }
        }

        usort($rows, fn($a, $b) => $a['name'] <=> $b['name']);

        return [$income, $expense, $activeCount, $rows];
    }

    // the most recent transaction date each farmer has ever posted, regardless of the
    // period asked for — this tells an agent who has gone quiet, not just who is quiet today
    private function lastActivityFor(Collection $farmerIds): Collection
    {
        return Transaction::query()
            ->whereIn('farmer_profile_id', $farmerIds)
            ->selectRaw('farmer_profile_id, MAX(transaction_date) as last_activity')
            ->groupBy('farmer_profile_id')
            ->pluck('last_activity', 'farmer_profile_id')
            ->map(fn($date) => $date === null ? null : Carbon::parse($date)->toDateString());
    }

    // rolling 7-day buckets counting backward from `to`, always at least four so the
    // chart never looks empty on a short filter. A bucket whose range falls entirely
    // before `from` is left at zero rather than reaching outside the filter for data —
    // padding buckets exist to fill the chart, not to leak extra history into it.
    public function weeklyTotalsFor(int $agentId, string $from, string $to): array
    {
        $farmerIds = FarmerProfile::query()
            ->where('assigned_agent_id', $agentId)
            ->pluck('id');

        $filterFrom = Carbon::parse($from);
        $filterTo = Carbon::parse($to);
        $bucketCount = max(4, (int) ceil(($filterFrom->diffInDays($filterTo) + 1) / 7));

        $buckets = [];

        for ($i = $bucketCount - 1; $i >= 0; $i--) {
            $bucketTo = $filterTo->copy()->subDays($i * 7);
            $bucketFrom = $bucketTo->copy()->subDays(6);

            $effectiveFrom = $bucketFrom->greaterThan($filterFrom) ? $bucketFrom : $filterFrom;
            $effectiveTo = $bucketTo->lessThan($filterTo) ? $bucketTo : $filterTo;
            $inFilter = !$effectiveFrom->greaterThan($effectiveTo);

            $buckets[] = [
                'from' => $bucketFrom->toDateString(),
                'to' => $bucketTo->toDateString(),
                'income' => $inFilter ? $this->weeklySum($farmerIds, $effectiveFrom, $effectiveTo, Transaction::INCOME) : 0,
                'expense' => $inFilter ? $this->weeklySum($farmerIds, $effectiveFrom, $effectiveTo, Transaction::EXPENSE) : 0,
            ];
        }

        return $buckets;
    }

    private function weeklySum(Collection $farmerIds, Carbon $from, Carbon $to, string $type): int
    {
        return (int) Transaction::query()
            ->whereIn('farmer_profile_id', $farmerIds)
            ->where('transaction_type', $type)
            ->where('is_provisional', false)
            ->whereDate('transaction_date', '>=', $from->toDateString())
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->sum('amount_minor');
    }
}
