<?php

namespace App\Services;

use App\Models\FarmerGroup;
use App\Models\FarmerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

// what an agent may learn about a farmer group: only groups holding at least one farmer
// assigned to them, full details only for those farmers, everyone else as a bare count
class FarmerGroupLookupService
{
    public function groupsFor(User $agent, ?int $communityId = null): Collection
    {
        return FarmerGroup::query()
            ->whereHas('farmerProfiles', fn(Builder $query) => $query->where('assigned_agent_id', $agent->id))
            ->when($communityId, fn(Builder $query) => $query->where('community_id', $communityId))
            ->withCount([
                'farmerProfiles as own_farmers_count' => fn(Builder $query) => $query->where('assigned_agent_id', $agent->id),
                'farmerProfiles as other_farmers_count' => fn(Builder $query) => $this->notMine($query, $agent),
            ])
            ->with('community:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn(FarmerGroup $group) => [
                'id' => $group->id,
                'name' => $group->name,
                'community_id' => $group->community_id,
                'community' => $group->community?->name,
                'is_active' => $group->is_active,
                'own_farmers_count' => $group->own_farmers_count,
                'other_farmers_count' => $group->other_farmers_count,
            ]);
    }

    // null means none of this agent's farmers are in the group, so as far as they know it is not there
    public function detail(User $agent, FarmerGroup $group): ?array
    {
        $own = $group->farmerProfiles()
            ->where('assigned_agent_id', $agent->id)
            ->with('user:id,surname,first_name,phone')
            ->get();

        if ($own->isEmpty()) {
            return null;
        }

        return [
            'group' => [
                'id' => $group->id,
                'name' => $group->name,
                'community' => $group->community?->name,
                'is_active' => $group->is_active,
            ],
            'own_farmers' => $own->map(fn(FarmerProfile $farmer) => [
                'id' => $farmer->uuid,
                'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                'phone' => $farmer->user?->phone,
                'identity_verified' => $farmer->identity_verified_at !== null,
                'identity_submitted' => $farmer->identity_number_hash !== null,
                'is_active' => $farmer->is_active,
            ])->values()->all(),
            'other_farmers_count' => $this->notMine($group->farmerProfiles()->getQuery(), $agent)->count(),
        ];
    }

    private function notMine(Builder $query, User $agent): Builder
    {
        return $query->where(
            fn(Builder $inner) => $inner->whereNull('assigned_agent_id')->orWhere('assigned_agent_id', '!=', $agent->id)
        );
    }
}
