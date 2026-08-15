<?php

use App\Models\User;
use App\Support\DashboardRouteResolver;

beforeEach(function () {
    $this->resolver = app(DashboardRouteResolver::class);
});

test('each role gets its own dashboard', function (string $role, string $expected) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($this->resolver->routeName($user))->toBe($expected);
})->with([
    ['admin', 'admin.dashboard'],
    ['agent', 'agent.dashboard'],
    ['vet', 'vet.dashboard'],
    ['adviser', 'adviser.dashboard'],
    ['supplier', 'supplier.dashboard'],
    ['farmer', 'farmer.dashboard'],
]);

test('a user with no role lands on the farmer dashboard', function () {
    $user = User::factory()->create();

    expect($this->resolver->routeName($user))->toBe('farmer.dashboard');
});

test('path gives the web address', function () {
    $user = User::factory()->create();
    $user->assignRole('admin');

    expect($this->resolver->path($user))->toBe('/admin/dashboard');
});
