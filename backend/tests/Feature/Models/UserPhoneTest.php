<?php

use App\Models\User;

test('a local number is stored in international form', function () {
    $user = User::factory()->create(['phone' => '0244445566']);

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('an international number is stored unchanged', function () {
    $user = User::factory()->create(['phone' => '0244445566']);

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('a number with spaces is cleaned before storing', function () {
    $user = User::factory()->create(['phone' => '024 444 5566']);

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('an update goes through the same cleaning', function () {
    $user = User::factory()->create(['phone' => '0244445566']);

    $user->update(['phone' => '0209998877']);

    expect($user->fresh()->phone)->toBe('0209998877');
});

test('the unique index catches the same person written two ways', function () {
    User::factory()->create(['phone' => '0244445566']);

    expect(fn() => User::factory()->create(['phone' => '0244445566']))
        ->toThrow(Illuminate\Database\QueryException::class);
});

test('the factory produces a number the normaliser accepts', function () {
    $user = User::factory()->create();

    expect(App\Support\Phone::normalise($user->phone))->toBe($user->phone);
});

test('every factory number is unique across a batch', function () {
    $users = User::factory()->count(50)->create();

    expect($users->pluck('phone')->unique()->count())->toBe(50);
});
