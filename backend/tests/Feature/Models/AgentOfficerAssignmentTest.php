<?php

use App\Enums\OfficerRole;
use App\Models\AgentOfficerAssignment;
use App\Models\User;
use Illuminate\Database\QueryException;

test('an assignment links an agent to an officer under a role, cast to the enum', function () {
    $agent = User::factory()->create();
    $officer = User::factory()->create();

    $assignment = AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => $officer->id,
        'role' => OfficerRole::Vet,
    ]);

    expect($assignment->role)->toBe(OfficerRole::Vet);
    expect($assignment->agent->id)->toBe($agent->id);
    expect($assignment->officer->id)->toBe($officer->id);
});

// the same route cannot be created twice, so an agent is never linked to
// the same officer for the same role by two different pivot rows
test('the same agent, officer and role cannot be linked twice', function () {
    $agent = User::factory()->create();
    $officer = User::factory()->create();

    AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => $officer->id,
        'role' => OfficerRole::Vet,
    ]);

    expect(fn() => AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => $officer->id,
        'role' => OfficerRole::Vet,
    ]))->toThrow(QueryException::class);
});

test('an agent can be linked to the same officer under a different role', function () {
    $agent = User::factory()->create();
    $officer = User::factory()->create();

    AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => $officer->id,
        'role' => OfficerRole::Vet,
    ]);

    $second = AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => $officer->id,
        'role' => OfficerRole::Adviser,
    ]);

    expect($second->exists)->toBeTrue();
});

test('scopeForRole only returns assignments for that role', function () {
    $agent = User::factory()->create();

    $vetLink = AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => User::factory()->create()->id,
        'role' => OfficerRole::Vet,
    ]);
    AgentOfficerAssignment::create([
        'agent_id' => $agent->id,
        'officer_id' => User::factory()->create()->id,
        'role' => OfficerRole::Adviser,
    ]);

    $result = AgentOfficerAssignment::query()->forRole(OfficerRole::Vet)->pluck('id');

    expect($result->all())->toBe([$vetLink->id]);
});
