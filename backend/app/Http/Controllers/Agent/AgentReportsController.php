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
        private readonly \App\Services\Ledger\Reports\IncomeAndExpenditureService $incomes,
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

    public function ranking(Request $request): Response
    {
        [$from, $to] = $this->periodFrom($request);
        $sort = $this->sortFrom($request);

        [,,, $rows] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return Inertia::render('Agent/Reports/Ranking', [
            'roster' => $this->ranked($rows, $sort),
            'sort' => $sort,
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printRanking(Request $request): ViewResponse
    {
        [$from, $to] = $this->periodFrom($request);
        $sort = $this->sortFrom($request);

        [,,, $rows] = $this->roster->totalsFor($request->user()->id, $from, $to, withRows: true);

        return view('reports.agent-ranking-print', [
            'agentName' => trim("{$request->user()->surname} {$request->user()->first_name}"),
            'from' => $from,
            'to' => $to,
            'sort' => $sort,
            'roster' => $this->ranked($rows, $sort),
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

    private function sortFrom(Request $request): string
    {
        $sort = $request->query('sort', 'income');

        return in_array($sort, ['income', 'net'], true) ? $sort : 'income';
    }

    public function farmerProfile(Request $request, \App\Models\FarmerProfile $farmer): Response
    {
        $this->guardFarmer($request->user(), $farmer);
        [$from, $to] = $this->periodFrom($request);

        return Inertia::render('Agent/Reports/FarmerProfile', [
            'farmer' => $this->profileData($farmer),
            'farm_units' => $this->farmUnitsData($farmer),
            'summary' => $this->financialSummary($farmer, $from, $to),
            'credit_score' => 'Not yet available',
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    public function printFarmerProfile(Request $request, \App\Models\FarmerProfile $farmer): ViewResponse
    {
        $this->guardFarmer($request->user(), $farmer);
        [$from, $to] = $this->periodFrom($request);

        return view('reports.agent-farmer-profile-print', [
            'agentName' => trim("{$request->user()->surname} {$request->user()->first_name}"),
            'farmer' => $this->profileData($farmer),
            'farmUnits' => $this->farmUnitsData($farmer),
            'summary' => $this->financialSummary($farmer, $from, $to),
            'creditScore' => 'Not yet available',
            'from' => $from,
            'to' => $to,
            'generatedAt' => now(),
        ]);
    }

    // a farmer this agent does not hold simply is not there, so nothing is learned by guessing
    private function guardFarmer(\App\Models\User $agent, \App\Models\FarmerProfile $farmer): void
    {
        abort_if($farmer->assigned_agent_id !== $agent->id, 404);
    }

    private function profileData(\App\Models\FarmerProfile $farmer): array
    {
        $farmer->load(['user', 'community', 'farmerGroup', 'farmTypes', 'identityVerifiedBy', 'registeredBy']);

        return [
            'id' => $farmer->uuid,
            'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
            'phone' => $farmer->user?->phone,
            'gender' => $farmer->gender,
            'date_of_birth' => $farmer->date_of_birth?->toDateString(),
            'home_address' => $farmer->home_address,
            'community' => $farmer->community?->name,
            'farmer_group' => $farmer->farmerGroup?->name,
            'is_active' => $farmer->is_active,
            'onboarded_at' => $farmer->onboarded_at?->toDateString(),
            'registered_by' => $farmer->registeredBy?->surname,
            'farm_types' => $farmer->farmTypes->pluck('name')->all(),
            'identity' => [
                'type' => $farmer->identity_type?->label(),
                'has_document' => $farmer->identity_number_hash !== null,
                'verified' => $farmer->identity_verified_at !== null,
                'verified_by' => $farmer->identityVerifiedBy?->surname,
            ],
        ];
    }

    private function farmUnitsData(\App\Models\FarmerProfile $farmer): array
    {
        return $farmer->farmUnits()
            ->with(['farmType:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn($unit) => [
                'name' => $unit->name,
                'farm_type' => $unit->farmType?->name,
                'capacity' => $unit->capacity,
                'capacity_unit' => $unit->capacity_unit,
                'is_approved' => $unit->isApproved(),
            ])
            ->all();
    }

    private function financialSummary(\App\Models\FarmerProfile $farmer, string $from, string $to): array
    {
        $report = $this->incomes->for(farmerProfileId: $farmer->id, from: $from, to: $to);

        return [
            'total_income' => $report->totalIncomeMinor,
            'total_expense' => $report->totalExpenseMinor,
            'net' => $report->totalIncomeMinor - $report->totalExpenseMinor,
        ];
    }

    // highest first; net is income minus expense since the roster row doesn't carry
    // a separate net figure
    private function ranked(array $rows, string $sort): array
    {
        usort($rows, function ($a, $b) use ($sort) {
            $valueA = $sort === 'net' ? $a['income'] - $a['expense'] : $a['income'];
            $valueB = $sort === 'net' ? $b['income'] - $b['expense'] : $b['income'];

            return $valueB <=> $valueA;
        });

        return array_values(array_map(
            fn($row, $index) => [...$row, 'net' => $row['income'] - $row['expense'], 'rank' => $index + 1],
            $rows,
            array_keys($rows),
        ));
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
