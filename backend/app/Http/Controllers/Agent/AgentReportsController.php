<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Agent\FarmerRosterService;
use Illuminate\Contracts\View\View as ViewResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\Agent\AgentIncomeSummaryService;

class AgentReportsController extends Controller
{
    public function __construct(
        private readonly FarmerRosterService $roster,
        private readonly AgentIncomeSummaryService $incomeSummary,
    ) {}

    public function index(Request $request): Response
    {
        $query = $request->string('q')->trim()->toString();

        return Inertia::render('Agent/Reports/Index', [
            'farmers' => $query === '' ? [] : $this->search($request->user(), $query),
            'query' => $query,
        ]);
    }

    public function activity(Request $request): Response
    {
        [$from, $to] = $this->periodFrom($request);

        [,,, $roster] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return Inertia::render('Agent/Reports/Activity', [
            'roster' => $roster,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printActivity(Request $request): ViewResponse
    {
        [$from, $to] = $this->periodFrom($request);

        [,,, $roster] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return view('reports.agent-activity-print', [
            'agentName' => trim("{$request->user()->surname} {$request->user()->first_name}"),
            'from' => $from,
            'to' => $to,
            'roster' => $roster,
            'generatedAt' => now(),
        ]);
    }

    public function dormant(Request $request): Response
    {
        [$from, $to] = $this->periodFrom($request);

        [,,, $rows] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return Inertia::render('Agent/Reports/Dormant', [
            'roster' => $this->sortDormant($rows),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printDormant(Request $request): ViewResponse
    {
        [$from, $to] = $this->periodFrom($request);

        [,,, $rows] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return view('reports.agent-dormant-print', [
            'agentName' => trim("{$request->user()->surname} {$request->user()->first_name}"),
            'from' => $from,
            'to' => $to,
            'roster' => $this->sortDormant($rows),
            'generatedAt' => now(),
        ]);
    }

    public function incomeSummary(Request $request): Response
    {
        [$from, $to] = $this->periodFrom($request);

        return Inertia::render('Agent/Reports/IncomeSummary', [
            'summary' => $this->incomeSummary->for($request->user()->id, $from, $to),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printIncomeSummary(Request $request): ViewResponse
    {
        [$from, $to] = $this->periodFrom($request);

        return view('reports.agent-income-summary-print', [
            'agentName' => trim("{$request->user()->surname} {$request->user()->first_name}"),
            'from' => $from,
            'to' => $to,
            'summary' => $this->incomeSummary->for($request->user()->id, $from, $to),
            'generatedAt' => now(),
        ]);
    }

    private function periodFrom(Request $request): array
    {
        return [
            $request->query('from', Carbon::now()->subDays(29)->toDateString()),
            $request->query('to', Carbon::now()->toDateString()),
        ];
    }

    // never-active farmers first, then the ones who went quiet longest ago
    private function sortDormant(array $rows): array
    {
        $dormant = array_values(array_filter($rows, fn($row) => $row['status'] === 'dormant'));

        usort($dormant, function ($a, $b) {
            if ($a['last_activity'] === null && $b['last_activity'] === null) {
                return 0;
            }
            if ($a['last_activity'] === null) {
                return -1;
            }
            if ($b['last_activity'] === null) {
                return 1;
            }

            return $a['last_activity'] <=> $b['last_activity'];
        });

        return $dormant;
    }

    // an empty query returns nothing rather than the whole book, so the page never
    // loads a long list by accident
    private function search(User $agent, string $query): array
    {
        $needle = '%' . mb_strtolower($query) . '%';

        return FarmerProfile::query()
            ->where('assigned_agent_id', $agent->id)
            ->where(function (Builder $outer) use ($needle) {
                $outer->whereHas('user', fn(Builder $inner) => $inner
                    ->whereRaw('LOWER(surname) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$needle]))
                    ->orWhereHas('community', fn(Builder $inner) => $inner
                        ->whereRaw('LOWER(name) LIKE ?', [$needle]));
            })
            ->with(['user:id,surname,first_name,phone', 'community:id,name'])
            ->limit(20)
            ->get()
            ->map(fn(FarmerProfile $farmer) => [
                'id' => $farmer->uuid,
                'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                'phone' => $farmer->user?->phone,
                'community' => $farmer->community?->name,
            ])
            ->all();
    }
}
