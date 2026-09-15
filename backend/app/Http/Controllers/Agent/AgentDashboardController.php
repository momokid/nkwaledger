<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Services\Agent\FarmerRosterService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AgentDashboardController extends Controller
{
    public function __construct(
        private readonly FarmerRosterService $roster,
        private readonly \App\Services\Agent\AgentActivityFeedService $activityFeed,
    ) {}

    public function index(Request $request): Response
    {
        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());
        [$prevFrom, $prevTo] = $this->previousPeriod($from, $to);

        [$income, $expense, $activeCount, $roster, $collected, $paidOut] = $this->roster->totalsFor(
            $request->user()->id,
            $from,
            $to,
            withRows: true,
        );
        [$prevIncome, $prevExpense] = $this->roster->totalsFor($request->user()->id, $prevFrom, $prevTo);

        return Inertia::render('Agent/Dashboard', [
            'summary' => $this->summaryFrom($income, $expense, $prevIncome, $prevExpense, $collected, $paidOut),
            'farmer_count' => $activeCount,
            'roster' => $roster,
            'activity_feed' => $this->activityFeed->recentFor($request->user()->id, $from, $to),
            'weekly_trend' => $this->roster->weeklyTotalsFor($request->user()->id, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    private function summaryFrom(
        int $income,
        int $expense,
        int $prevIncome,
        int $prevExpense,
        int $collected,
        int $paidOut,
    ): array {
        $net = $income - $expense;
        $prevNet = $prevIncome - $prevExpense;

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $net,
            // net profit stays based on earned/incurred above; these two are shown
            // as a secondary line under the existing cards, not new cards of their
            // own - same split as the farmer dashboard, summed across the roster
            'cash_collected' => $collected,
            'cash_paid_out' => $paidOut,
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
