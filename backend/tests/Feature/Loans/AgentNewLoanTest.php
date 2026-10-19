<?php

// The agent's New Loan page: a search box that finds only the agent's own farmers, then the usual loan forms.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Models\FarmerProfile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    buildLoanWorld($this);

    $this->find = fn($user, string $q) => $this->actingAs($user)->postJson('/agent/loans/farmer-search', ['q' => $q]);
});

test('an agent opens the New Loan page', function () {
    $this->actingAs($this->agent)->get('/agent/loans/new')->assertOk()
        ->assertInertia(fn(Assert $page) => $page->component('Agent/Loans/New'));
});

test('a farmer cannot open the agent New Loan page', function () {
    $this->actingAs($this->farmerUser)->get('/agent/loans/new')->assertForbidden();
});

test('the search finds the agent\'s own farmer by name', function () {
    $name = $this->profile->user->first_name;

    $found = ($this->find)($this->agent, $name)->assertOk()->json();

    expect(collect($found)->pluck('id')->all())->toContain($this->profile->uuid)
        ->and(collect($found)->every(fn($row) => array_keys($row) === ['id', 'name', 'community']))->toBeTrue();
});

test('the search never returns a farmer assigned to another agent', function () {
    $name = $this->profile->user->first_name;
    $theirs = FarmerProfile::factory()->create([
        'user_id' => User::factory()->create(['first_name' => $name])->id,
        'assigned_agent_id' => $this->otherAgent->id,
    ]);

    $found = ($this->find)($this->agent, $name)->assertOk()->json();

    expect(collect($found)->pluck('id')->all())->not->toContain($theirs->uuid);
});

test('the search needs at least two letters', function () {
    ($this->find)($this->agent, 'a')->assertStatus(422);
});

test('only an agent can search', function () {
    ($this->find)($this->farmerUser, $this->profile->user->first_name)->assertForbidden();
});
