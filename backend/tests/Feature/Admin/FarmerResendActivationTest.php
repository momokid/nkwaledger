<?php

use App\Models\FarmerProfile;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->farmer->user->forceFill(['phone_verified_at' => null, 'password' => null])->save();
});

test('a guest is redirected to login', function () {
    $this->post("/admin/farmers/{$this->farmer->uuid}/resend")->assertRedirect('/login');
});

test('an agent can send a fresh code', function () {
    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend")
        ->assertSessionDoesntHaveErrors();

    expect(OtpCode::where('identifier', $this->farmer->user->phone)
        ->where('type', 'invitation')
        ->exists())->toBeTrue();
});

test('an admin can send a fresh code', function () {
    $this->actingAs($this->admin)->post("/admin/farmers/{$this->farmer->uuid}/resend")
        ->assertSessionDoesntHaveErrors();

    expect(OtpCode::where('identifier', $this->farmer->user->phone)->exists())->toBeTrue();
});

test('an agent cannot send a code for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);

    $this->actingAs($this->agent)->post("/agent/farmers/{$other->uuid}/resend")->assertNotFound();

    expect(OtpCode::where('identifier', $other->user->phone)->exists())->toBeFalse();
});

test('a user without the create permission cannot send a code', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farmers.view');

    $this->actingAs($vet)->post("/admin/farmers/{$this->farmer->uuid}/resend")->assertForbidden();
});

// the number is already proved, so there is nothing left to confirm
test('a farmer whose phone is already confirmed gets no code', function () {
    $this->farmer->user->forceFill(['phone_verified_at' => now()])->save();

    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend")
        ->assertSessionHasErrors();

    expect(OtpCode::where('identifier', $this->farmer->user->phone)->exists())->toBeFalse();
});

// each message costs money, so a second click within the hour changes nothing
test('a second send while a code is still live sends nothing new', function () {
    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend");

    $first = OtpCode::where('identifier', $this->farmer->user->phone)->count();

    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend");

    expect(OtpCode::where('identifier', $this->farmer->user->phone)->count())->toBe($first);
});

test('a send once the old code has run out issues a new one', function () {
    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend");

    OtpCode::where('identifier', $this->farmer->user->phone)
        ->update(['expires_at' => now()->subMinute()]);

    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend");

    expect(OtpCode::where('identifier', $this->farmer->user->phone)->count())->toBe(2);
});

// the page has to know whether to offer the button
test('the farmer page says whether a code is still live', function () {
    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('farmer.has_live_code', false));
});

test('the page reports a live code once one is sent', function () {
    $this->actingAs($this->agent)->post("/agent/farmers/{$this->farmer->uuid}/resend");

    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}")
        ->assertInertia(fn($page) => $page->where('farmer.has_live_code', true));
});
