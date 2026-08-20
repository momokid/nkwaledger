<?php

use App\Models\User;
use App\Support\OtpOutcomeResolver;

beforeEach(function () {
    $this->resolver = app(OtpOutcomeResolver::class);
});

test('a login code logs the person in', function () {
    expect($this->resolver->authenticates('login'))->toBeTrue();
});

test('a registration code logs the person in', function () {
    expect($this->resolver->authenticates('registration'))->toBeTrue();
});

// the account has no password yet, so a session must not exist until they set one
test('an invitation code does not log the person in', function () {
    expect($this->resolver->authenticates('invitation'))->toBeFalse();
});

test('a login code proves the phone', function () {
    expect($this->resolver->verifiesPhone('login'))->toBeTrue();
});

test('a registration code does not prove the phone on its own', function () {
    expect($this->resolver->verifiesPhone('registration'))->toBeFalse();
});

// verification is recorded once activation finishes, not partway through
test('an invitation code does not prove the phone yet', function () {
    expect($this->resolver->verifiesPhone('invitation'))->toBeFalse();
});

test('a login code sends each role to its own dashboard', function (string $role, string $expected) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($this->resolver->path('login', $user))->toBe($expected);
})->with([
    ['admin', '/admin/dashboard'],
    ['agent', '/agent/dashboard'],
    ['farmer', '/farmer/dashboard'],
]);

test('a registration code sends them to a dashboard too', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    expect($this->resolver->path('registration', $user))->toBe('/farmer/dashboard');
});

test('an invitation code sends them to set a password', function () {
    $user = User::factory()->create();
    $user->assignRole('agent');

    expect($this->resolver->path('invitation', $user))->toBe('/set-password');
});

test('an invitation with no matching account still goes to set a password', function () {
    expect($this->resolver->path('invitation', null))->toBe('/set-password');
});

test('a login code with no matching account falls back to the farmer dashboard', function () {
    expect($this->resolver->path('login', null))->toBe('/farmer/dashboard');
});

test('an unknown type is refused rather than guessed at', function () {
    expect(fn() => $this->resolver->authenticates('nonsense'))
        ->toThrow(InvalidArgumentException::class);
});
