<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
});

test('a guest is redirected to login', function () {
    $this->get('/agent/reports')->assertRedirect('/login');
});

test('an agent sees the reports page', function () {
    $this->actingAs($this->agent)->get('/agent/reports')
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Agent/Reports/Index'));
});

test('a user without the view permission is refused', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get('/agent/reports')->assertForbidden();
});

test('an empty query returns no farmers', function () {
    FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    $this->actingAs($this->agent)->get('/agent/reports')
        ->assertInertia(fn($page) => $page->where('farmers', []));
});

test('searching finds an assigned farmer by name', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $farmer->user->update(['surname' => 'Mensah', 'first_name' => 'Ama']);

    $this->actingAs($this->agent)->get('/agent/reports?q=Mensah')
        ->assertInertia(fn($page) => $page
            ->where('farmers.0.name', 'Mensah Ama')
            ->where('query', 'Mensah'));
});

test('searching finds an assigned farmer by phone', function () {
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $farmer->user->update(['phone' => '0244001122']);

    $this->actingAs($this->agent)->get('/agent/reports?q=0244001122')
        ->assertInertia(fn($page) => $page->has('farmers', 1));
});

test('searching finds an assigned farmer by community', function () {
    $community = Community::factory()->create(['name' => 'Effiduase']);
    FarmerProfile::factory()->create([
        'assigned_agent_id' => $this->agent->id,
        'community_id' => $community->id,
    ]);

    $this->actingAs($this->agent)->get('/agent/reports?q=Effiduase')
        ->assertInertia(fn($page) => $page->has('farmers', 1));
});

test('search never returns another agent\'s farmer', function () {
    $otherAgent = User::factory()->create();
    $otherAgent->assignRole('agent');
    $farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $otherAgent->id]);
    $farmer->user->update(['surname' => 'Mensah']);

    $this->actingAs($this->agent)->get('/agent/reports?q=Mensah')
        ->assertInertia(fn($page) => $page->where('farmers', []));
});
