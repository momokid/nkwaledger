<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'verified.phone'])
        ->get('/test-guarded', fn() => response('ok'))
        ->name('test.guarded');

    Route::getRoutes()->refreshNameLookups();
});

test('a verified user passes through', function () {
    $user = User::factory()->verified()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/test-guarded')->assertOk();
});

test('an unverified farmer is sent to the farmer dashboard', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/test-guarded')->assertRedirect('/farmer/dashboard');
});

test('an unverified admin is sent to the admin dashboard', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->get('/test-guarded')->assertRedirect('/admin/dashboard');
});

test('the block message says nothing technical', function () {
    $user = User::factory()->unverified()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/test-guarded')
        ->assertSessionHas('error', 'Please verify your phone number to continue.');
});

// a tracked role with an email on file defaults to the email channel (Step C), so the
// block message should point them at their inbox, not a phone number
test('a tracked role with an email on file is told to check their email', function () {
    $user = User::factory()->unverified()->create(['email' => 'agent@nkwaledger.com']);
    $user->assignRole('agent');

    $this->actingAs($user)->get('/test-guarded')
        ->assertSessionHas('error', 'Please check your email to verify your account.');
});

// no email on file means the code still goes by sms, same as PhoneVerificationController::send()
test('a tracked role with no email on file still sees the phone wording', function () {
    $user = User::factory()->unverified()->create(['email' => null]);
    $user->assignRole('agent');

    $this->actingAs($user)->get('/test-guarded')
        ->assertSessionHas('error', 'Please verify your phone number to continue.');
});
