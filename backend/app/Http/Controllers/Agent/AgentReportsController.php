<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = $request->string('q')->trim()->toString();

        return Inertia::render('Agent/Reports/Index', [
            'farmers' => $query === '' ? [] : $this->search($request->user(), $query),
            'query' => $query,
        ]);
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
