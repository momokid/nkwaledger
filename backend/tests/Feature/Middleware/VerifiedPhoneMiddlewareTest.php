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
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/test-guarded')->assertRedirect('/farmer/dashboard');
});

test('an unverified admin is sent to the admin dashboard', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    $this->actingAs($user)->get('/test-guarded')->assertRedirect('/admin/dashboard');
});

test('the block message says nothing technical', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/test-guarded')
        ->assertSessionHas('error', 'Please verify your phone number to continue.');
});

test('an unverified user can still reach the otp page', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $this->actingAs($user)->get('/verify-otp')->assertOk();
});
