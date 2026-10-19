<?php

// The USSD simulator: a terminal command and a local page that play a session against the same flow the gateway calls.
// Both refuse to run anywhere but local and testing.

use App\Models\FarmerProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->farmerUser = User::factory()->create(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi', 'ussd_pin_hash' => Hash::make('4829')]);
    $this->farmerUser->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->asProduction = fn() => app()->detectEnvironment(fn() => 'production');
    $this->dialPage = fn(array $body) => $this->postJson('/dev/ussd/dial', $body + ['phone' => '0244445566', 'session' => 'sim-1']);
});

test('the command plays a session: PIN, menu, exit', function () {
    $this->artisan('ussd:simulate', ['phone' => '0244445566'])
        ->expectsOutputToContain('Enter your PIN.')
        ->expectsQuestion('Reply', '4829')
        ->expectsOutputToContain('Hello Kofi.')
        ->expectsQuestion('Reply', '6')
        ->expectsOutputToContain('Thank you.')
        ->assertExitCode(0);
});

test('the command refuses to run in production', function () {
    ($this->asProduction)();

    $this->artisan('ussd:simulate', ['phone' => '0244445566'])->assertExitCode(1);
});

test('the page opens locally', function () {
    $this->get('/dev/ussd')->assertOk();
});

test('the page dials the same flow', function () {
    $first = ($this->dialPage)(['new' => true, 'input' => ''])->assertOk();
    expect($first->json('message'))->toContain('Enter your PIN.')->and($first->json('continueSession'))->toBeTrue();

    $menu = ($this->dialPage)(['new' => false, 'input' => '4829']);
    expect($menu->json('message'))->toContain('Hello Kofi.');
});

test('the page and its dial endpoint do not exist in production', function () {
    ($this->asProduction)();

    $this->get('/dev/ussd')->assertNotFound();
    ($this->dialPage)(['new' => true, 'input' => ''])->assertNotFound();
});
