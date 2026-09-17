<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly AdminAnalyticsService $analytics) {}

    public function index(Request $request): Response
    {
        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());
        $sort = $this->sortFrom($request);

        return Inertia::render('Admin/Dashboard', [
            'snapshot' => $this->analytics->platformSnapshot($from, $to),
            'leaderboard' => $this->analytics->agentLeaderboard($from, $to, $sort),
            'sort' => $sort,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    private function sortFrom(Request $request): string
    {
        $sort = $request->query('sort', 'net');

        return in_array($sort, ['net', 'activity'], true) ? $sort : 'net';
    }
}
