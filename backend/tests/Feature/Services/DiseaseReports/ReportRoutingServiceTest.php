<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\User;
use App\Services\DiseaseReports\ReportRoutingService;

beforeEach(function () {
    $this->service = app(ReportRoutingService::class);

    $this->cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestockCategory = FarmTypeCategory::create(['name' => 'Livestock']);
    $this->aquaticCategory = FarmTypeCategory::create(['name' => 'Aquatic']);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
});

function farmUnitWithCategory(FarmTypeCategory $category): FarmUnit
{
    $farmType = FarmType::factory()->withCategory($category)->create();

    return FarmUnit::factory()->create(['farm_type_id' => $farmType->id]);
}

describe('routeFor', function () {
    it('routes a crop farm unit to the adviser role', function () {
        $unit = farmUnitWithCategory($this->cropCategory);

        [$category, $role] = $this->service->routeFor($unit);

        expect($category)->toBe('Crop');
        expect($role)->toBe(OfficerRole::Adviser);
    });

    it('routes a livestock farm unit to the vet role', function () {
        $unit = farmUnitWithCategory($this->livestockCategory);

        [$category, $role] = $this->service->routeFor($unit);

        expect($category)->toBe('Livestock');
        expect($role)->toBe(OfficerRole::Vet);
    });

    it('routes an aquatic farm unit to the vet role', function () {
        $unit = farmUnitWithCategory($this->aquaticCategory);

        [$category, $role] = $this->service->routeFor($unit);

        expect($category)->toBe('Aquatic');
        expect($role)->toBe(OfficerRole::Vet);
    });

    it('refuses to route a farm unit whose type has no category', function () {
        $farmType = FarmType::factory()->create(['category_id' => null]);
        $unit = FarmUnit::factory()->create(['farm_type_id' => $farmType->id]);

        expect(fn() => $this->service->routeFor($unit))->toThrow(RuntimeException::class);
    });
});

describe('officerFor', function () {
    it('returns null when the farmer has no assigned agent', function () {
        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => null]);

        expect($this->service->officerFor($farmer, OfficerRole::Vet))->toBeNull();
    });

    it('returns null when the assigned agent has no officer of that role yet', function () {
        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        expect($this->service->officerFor($farmer, OfficerRole::Vet))->toBeNull();
    });

    it('returns the vet linked to the farmer\'s agent', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        AgentOfficerAssignment::create([
            'agent_id' => $this->agent->id,
            'officer_id' => $vet->id,
            'role' => OfficerRole::Vet,
        ]);

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        expect($this->service->officerFor($farmer, OfficerRole::Vet)->id)->toBe($vet->id);
    });

    it('does not return an officer linked for a different role', function () {
        $adviser = User::factory()->create();
        $adviser->assignRole('adviser');

        AgentOfficerAssignment::create([
            'agent_id' => $this->agent->id,
            'officer_id' => $adviser->id,
            'role' => OfficerRole::Adviser,
        ]);

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        expect($this->service->officerFor($farmer, OfficerRole::Vet))->toBeNull();
    });

    // an agent can hold several vets; the earliest link wins, so the outcome is deterministic
    it('picks the officer linked first when the agent has several of the same role', function () {
        $olderVet = User::factory()->create();
        $olderVet->assignRole('vet');
        $newerVet = User::factory()->create();
        $newerVet->assignRole('vet');

        AgentOfficerAssignment::create([
            'agent_id' => $this->agent->id,
            'officer_id' => $olderVet->id,
            'role' => OfficerRole::Vet,
            'created_at' => now()->subDay(),
        ]);
        AgentOfficerAssignment::create([
            'agent_id' => $this->agent->id,
            'officer_id' => $newerVet->id,
            'role' => OfficerRole::Vet,
        ]);

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        expect($this->service->officerFor($farmer, OfficerRole::Vet)->id)->toBe($olderVet->id);
    });
});

describe('autoAssignWaitingReports', function () {
    it('assigns every waiting report of the matching role for that agent\'s farmers', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
        $otherFarmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        $waiting = DiseaseReport::factory()->create([
            'farmer_profile_id' => $farmer->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => null,
        ]);
        $alsoWaiting = DiseaseReport::factory()->create([
            'farmer_profile_id' => $otherFarmer->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => null,
        ]);

        $count = $this->service->autoAssignWaitingReports($this->agent->id, OfficerRole::Vet, $vet);

        expect($count)->toBe(2);
        expect($waiting->fresh()->assigned_officer_id)->toBe($vet->id);
        expect($alsoWaiting->fresh()->assigned_officer_id)->toBe($vet->id);
    });

    it('leaves a different role\'s waiting reports untouched', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        $adviserReport = DiseaseReport::factory()->create([
            'farmer_profile_id' => $farmer->id,
            'routed_role' => OfficerRole::Adviser,
            'assigned_officer_id' => null,
        ]);

        $this->service->autoAssignWaitingReports($this->agent->id, OfficerRole::Vet, $vet);

        expect($adviserReport->fresh()->assigned_officer_id)->toBeNull();
    });

    it('leaves another agent\'s waiting reports untouched', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');

        $otherAgent = User::factory()->create();
        $otherAgent->assignRole('agent');
        $otherFarmer = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);

        $report = DiseaseReport::factory()->create([
            'farmer_profile_id' => $otherFarmer->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => null,
        ]);

        $this->service->autoAssignWaitingReports($this->agent->id, OfficerRole::Vet, $vet);

        expect($report->fresh()->assigned_officer_id)->toBeNull();
    });

    it('leaves an already-assigned report untouched', function () {
        $vet = User::factory()->create();
        $vet->assignRole('vet');
        $originalOfficer = User::factory()->create();
        $originalOfficer->assignRole('vet');

        $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

        $report = DiseaseReport::factory()->create([
            'farmer_profile_id' => $farmer->id,
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => $originalOfficer->id,
        ]);

        $this->service->autoAssignWaitingReports($this->agent->id, OfficerRole::Vet, $vet);

        expect($report->fresh()->assigned_officer_id)->toBe($originalOfficer->id);
    });
});
