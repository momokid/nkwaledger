<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Agent\AgentActivityFeedService;
use App\Services\Agent\FarmerRosterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AgentDetailController extends Controller
{
    public function __construct(
        private readonly FarmerRosterService $roster,
        private readonly AgentActivityFeedService $activityFeed,
    ) {}

    public function show(Request $request, User $agent): JsonResponse
    {
        abort_unless($agent->hasRole('agent'), 404);

        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        [$income, $expense, $activeCount, $rosterRows, $collected, $paidOut] = $this->roster->totalsFor(
            $agent->id,
            $from,
            $to,
            withRows: true,
        );

        return response()->json([
            'summary' => [
                'total_income' => $income,
                'total_expense' => $expense,
                'net' => $income - $expense,
                'cash_collected' => $collected,
                'cash_paid_out' => $paidOut,
            ],
            'farmer_count' => $activeCount,
            'roster' => $rosterRows,
            'activity_feed' => $this->activityFeed->recentFor($agent->id, $from, $to),
            'weekly_trend' => $this->roster->weeklyTotalsFor($agent->id, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }
}
