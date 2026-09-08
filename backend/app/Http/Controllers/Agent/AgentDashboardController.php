<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $farmerIds = FarmerProfile::query()
            ->where('assigned_agent_id', $request->user()->id)
            ->pluck('id');

        [$income, $expense, $activeCount] = $this->totalsFor($farmerIds, $from, $to);
        [$prevIncome, $prevExpense] = $this->totalsFor($farmerIds, $prevFrom, $prevTo);

        return Inertia::render('Agent/Dashboard', [
            'summary' => $this->summaryFrom($income, $expense, $prevIncome, $prevExpense),
            'farmer_count' => $activeCount,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    // walks every assigned farmer's own report and adds them up; a farmer counts as
    // active only if something moved through their books in this stretch of time
    private function totalsFor($farmerIds, string $from, string $to): array
    {
        $income = 0;
        $expense = 0;
        $activeCount = 0;

        foreach ($farmerIds as $farmerId) {
            $report = $this->incomes->for(farmerProfileId: $farmerId, from: $from, to: $to);

            $income += $report->totalIncomeMinor;
            $expense += $report->totalExpenseMinor;

            if ($report->totalIncomeMinor > 0 || $report->totalExpenseMinor > 0) {
                $activeCount++;
            }
        }

        return [$income, $expense, $activeCount];
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
