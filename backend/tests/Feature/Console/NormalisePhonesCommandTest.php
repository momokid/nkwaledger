<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;

// writes straight to the table so the model mutator cannot clean it on the way in
function storeRawPhone(User $user, string $phone): void
{
    DB::table('users')->where('id', $user->id)->update(['phone' => $phone]);
}

test('a number already in local form is left alone', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '0244445566');

    $this->artisan('phones:normalise')->assertSuccessful();

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('an international number is converted', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '+233244445566');

    $this->artisan('phones:normalise');

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('a number with spaces is cleaned', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '024 444 5566');

    $this->artisan('phones:normalise');

    expect($user->fresh()->phone)->toBe('0244445566');
});

test('a number the normaliser rejects is left untouched', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '+233299000001');

    $this->artisan('phones:normalise');

    expect($user->fresh()->phone)->toBe('+233299000001');
});

test('a rejected number is reported so it can be fixed by hand', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '+233299000001');

    $this->artisan('phones:normalise')
        ->expectsOutputToContain('+233299000001')
        ->assertSuccessful();
});

// converting would hit the unique index, so the row must survive untouched
test('a row that would collide is skipped', function () {
    $keeper = User::factory()->create();
    storeRawPhone($keeper, '0244445566');

    $duplicate = User::factory()->create();
    storeRawPhone($duplicate, '+233244445566');

    $this->artisan('phones:normalise')->assertSuccessful();

    expect($duplicate->fresh()->phone)->toBe('+233244445566');
    expect($keeper->fresh()->phone)->toBe('0244445566');
});

test('a collision is reported', function () {
    $keeper = User::factory()->create();
    storeRawPhone($keeper, '0244445566');

    $duplicate = User::factory()->create();
    storeRawPhone($duplicate, '+233244445566');

    $this->artisan('phones:normalise')->expectsOutputToContain('duplicate');
});

test('a dry run writes nothing', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '+233244445566');

    $this->artisan('phones:normalise --dry-run')->assertSuccessful();

    expect($user->fresh()->phone)->toBe('+233244445566');
});

test('a dry run still shows what would change', function () {
    $user = User::factory()->create();
    storeRawPhone($user, '+233244445566');

    $this->artisan('phones:normalise --dry-run')->expectsOutputToContain('0244445566');
});

test('it reports how many were converted', function () {
    $first = User::factory()->create();
    storeRawPhone($first, '+233244445566');

    $second = User::factory()->create();
    storeRawPhone($second, '233209998877');

    $this->artisan('phones:normalise')->expectsOutputToContain('2');
});
