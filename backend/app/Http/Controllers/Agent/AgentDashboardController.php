<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\Transaction;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AgentDashboardController extends Controller
{
    public function __construct(
        private readonly IncomeAndExpenditureService $incomes,
    ) {}

    public function index(Request $request): Response
    {
        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        $farmers = FarmerProfile::query()
            ->where('assigned_agent_id', $request->user()->id)
            ->with(['user:id,surname,first_name', 'community:id,name'])
            ->get();

        $lastActivity = $this->lastActivityFor($farmers->pluck('id'));

        [$income, $expense, $activeCount, $roster] = $this->totalsFor($farmers, $from, $to, $lastActivity);
        [$prevIncome, $prevExpense] = $this->totalsFor($farmers, $prevFrom, $prevTo);

        return Inertia::render('Agent/Dashboard', [
            'summary' => $this->summaryFrom($income, $expense, $prevIncome, $prevExpense),
            'farmer_count' => $activeCount,
            'roster' => $roster,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    // walks every assigned farmer's own report and adds them up; a farmer counts as
    // active only if something moved through their books in this stretch of time.
    // when $lastActivity is given, the same pass also builds the roster row, so
    // each farmer's report is only pulled once.
    private function totalsFor(Collection $farmers, string $from, string $to, ?Collection $lastActivity = null): array
    {
        $income = 0;
        $expense = 0;
        $activeCount = 0;
        $roster = [];

        foreach ($farmers as $farmer) {
            $report = $this->incomes->for(farmerProfileId: $farmer->id, from: $from, to: $to);

            $income += $report->totalIncomeMinor;
            $expense += $report->totalExpenseMinor;

            $isActive = $report->totalIncomeMinor > 0 || $report->totalExpenseMinor > 0;

            if ($isActive) {
                $activeCount++;
            }

            if ($lastActivity !== null) {
                $roster[] = [
                    'id' => $farmer->uuid,
                    'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                    'community' => $farmer->community?->name,
                    'last_activity' => $lastActivity->get($farmer->id),
                    'income' => $report->totalIncomeMinor,
                    'expense' => $report->totalExpenseMinor,
                    'status' => $isActive ? 'active' : 'dormant',
                ];
            }
        }

        usort($roster, fn($a, $b) => $a['name'] <=> $b['name']);

        return [$income, $expense, $activeCount, $roster];
    }

    private function lastActivityFor(Collection $farmerIds): Collection
    {
        return Transaction::query()
            ->whereIn('farmer_profile_id', $farmerIds)
            ->selectRaw('farmer_profile_id, MAX(transaction_date) as last_activity')
            ->groupBy('farmer_profile_id')
            ->pluck('last_activity', 'farmer_profile_id')
            ->map(fn($date) => $date === null ? null : Carbon::parse($date)->toDateString());
    }

    private function summaryFrom(int $income, int $expense, int $prevIncome, int $prevExpense): array
    {
        $net = $income - $expense;
        $prevNet = $prevIncome - $prevExpense;

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $net,
            'trends' => [
                'income' => $this->trend($income, $prevIncome, higherIsGood: true),
                'expense' => $this->trend($expense, $prevExpense, higherIsGood: false),
                'net' => $this->trend($net, $prevNet, higherIsGood: true),
            ],
        ];
    }

    private function previousPeriod(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $lengthInDays = $start->diffInDays($end) + 1;

        $previousTo = $start->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($lengthInDays - 1);

        return [$previousFrom->toDateString(), $previousTo->toDateString()];
    }

    private function trend(int $current, int $previous, bool $higherIsGood): array
    {
        $direction = $current <=> $previous;

        return [
            'direction' => $direction > 0 ? 'up' : ($direction < 0 ? 'down' : 'flat'),
            'percent' => $previous === 0 ? null : (int) round(abs($current - $previous) / abs($previous) * 100),
            'good' => $higherIsGood ? $current >= $previous : $current <= $previous,
        ];
    }
}
