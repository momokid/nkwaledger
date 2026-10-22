<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// the last 90 days of the reports sent to this officer, counted three ways
class TrendsController extends Controller
{
    public function index(Request $request): Response
    {
        $reports = DiseaseReport::query()
            ->where('assigned_officer_id', $request->user()->id)
            ->where('created_at', '>=', now()->subDays(90))
            ->with(['officerHealthCategory', 'healthCategory', 'farmUnit.community'])
            ->get();

        $vet = $request->user()->hasRole('vet');

        return Inertia::render('Officer/Trends', [
            'totals' => [
                'ninety' => $reports->count(),
                'thirty' => $reports->where('created_at', '>=', now()->subDays(30))->count(),
            ],
            'problems' => $this->problems($reports),
            'weeks' => $this->weeks($reports),
            'communities' => $reports
                ->groupBy(fn(DiseaseReport $r) => $r->farmUnit?->community?->name)
                ->reject(fn($group, $name) => $name === '')
                ->map(fn($group, $name) => ['name' => $name, 'count' => $group->count()])
                ->sortBy([['count', 'desc'], ['name', 'asc']])
                ->take(5)
                ->values(),
            'field' => $vet ? 'livestock' : 'crops',
            'basePath' => $vet ? '/vet' : '/adviser',
        ]);
    }

    private function problems($reports): array
    {
        $groups = $reports->groupBy(fn(DiseaseReport $r) => $r->effectiveHealthCategory()?->uuid ?? 'none');

        $named = $groups->reject(fn($group, $uuid) => $uuid === 'none')
            ->map(fn($group, $uuid) => [
                'uuid' => $uuid,
                'name' => $group->first()->effectiveHealthCategory()->name,
                'count' => $group->count(),
            ])
            ->sortBy([['count', 'desc'], ['name', 'asc']])
            ->values()
            ->take(10)
            ->all();

        // "not yet categorised" always goes last so it never hides a real problem
        if ($groups->has('none')) {
            $named[] = ['uuid' => 'none', 'name' => 'Not yet categorised', 'count' => $groups['none']->count()];
        }

        return $named;
    }

    // one bar per Monday-to-Sunday week, empty weeks included, oldest first
    private function weeks($reports): array
    {
        $counts = $reports->countBy(fn(DiseaseReport $r) => $r->created_at->copy()->startOfWeek()->toDateString());

        $weeks = [];
        for ($week = now()->subDays(90)->startOfWeek(); $week->lte(now()); $week = $week->copy()->addWeek()) {
            $weeks[] = ['week_start' => $week->toDateString(), 'count' => $counts[$week->toDateString()] ?? 0];
        }

        return $weeks;
    }
}
