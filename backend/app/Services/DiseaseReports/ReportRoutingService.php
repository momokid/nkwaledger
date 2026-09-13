<?php

namespace App\Services\DiseaseReports;

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use RuntimeException;

class ReportRoutingService
{
    // no AI classification needed — the farm unit's own category already
    // says what kind of problem this is
    public function routeFor(FarmUnit $farmUnit): array
    {
        $category = $farmUnit->farmType?->category?->name;

        if ($category === null) {
            throw new RuntimeException('This farm unit has no category to route a report by.');
        }

        return [$category, OfficerRole::fromFarmCategory($category)];
    }

    // the officer this farmer's agent already holds for this role; null sends it to the admin queue
    public function officerFor(FarmerProfile $farmer, OfficerRole $role): ?User
    {
        if ($farmer->assigned_agent_id === null) {
            return null;
        }

        return AgentOfficerAssignment::query()
            ->forRole($role)
            ->where('agent_id', $farmer->assigned_agent_id)
            ->oldest()
            ->first()
            ?->officer;
    }

    // called the instant admin links an agent to an officer: anything of that
    // agent's already waiting in the admin queue for this role moves to them at once
    public function autoAssignWaitingReports(int $agentId, OfficerRole $role, User $officer): int
    {
        return DiseaseReport::query()
            ->awaitingOfficer()
            ->where('routed_role', $role)
            ->whereHas('farmerProfile', fn($query) => $query->where('assigned_agent_id', $agentId))
            ->update(['assigned_officer_id' => $officer->id]);
    }
}
