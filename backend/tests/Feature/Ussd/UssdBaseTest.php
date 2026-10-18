<?php

// USSD slice 1: a farmer is let in by phone number and a server-side PIN, sets the PIN on the first dial,
// and sees the menu. Anything else about the number gets one and the same answer.

use App\Models\FarmerProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser = User::factory()->create(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi']);
    $this->farmerUser->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    // one reply per call: the same session id carries the farmer through the steps
    $this->dial = fn(string $input = '', bool $new = false, string $phone = '233244445566', string $secret = 'test-secret') => $this->postJson(
        "/ussd/callback/{$secret}",
        ['sessionID' => 's-1', 'msisdn' => $phone, 'newSession' => $new, 'userData' => $input],
    );
    $this->text = fn($response) => $response->json('message');
});

test('a wrong secret in the address gets nothing', function () {
    ($this->dial)('', true, secret: 'nope')->assertNotFound();
});

test('an unknown number, a staff number and a blocked farmer all get the same answer', function () {
    $staff = User::factory()->create(['phone' => '0201112233', 'phone_verified_at' => now()]);
    $staff->assignRole('agent');
    $blocked = User::factory()->create(['phone' => '0551112233', 'phone_verified_at' => now(), 'is_active' => false]);
    $blocked->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $blocked->id]);

    $answers = collect(['233279998877', '233201112233', '233551112233'])->map(fn($phone) => ($this->dial)('', true, $phone)->assertOk()->json());

    expect($answers->pluck('message')->unique())->toHaveCount(1)
        ->and($answers->pluck('continueSession')->unique()->all())->toBe([false]);
});

test('a farmer with no PIN is asked to set one and the PIN is kept hashed', function () {
    $first = ($this->dial)('', true)->assertOk();
    expect($first->json('continueSession'))->toBeTrue();

    $weak = ($this->dial)('1111');
    expect($weak->json('continueSession'))->toBeTrue()
        ->and($this->farmerUser->fresh()->ussd_pin_hash)->toBeNull();

    ($this->dial)('4829')->assertOk();
    $done = ($this->dial)('4829')->assertOk();

    $stored = $this->farmerUser->fresh()->ussd_pin_hash;

    expect($done->json('continueSession'))->toBeTrue()
        ->and($stored)->not->toBe('4829')
        ->and(Hash::check('4829', $stored))->toBeTrue();
});

test('two different PINs are not saved', function () {
    ($this->dial)('', true);
    ($this->dial)('4829');
    ($this->dial)('4830');

    expect($this->farmerUser->fresh()->ussd_pin_hash)->toBeNull();
});

describe('with a PIN', function () {
    beforeEach(function () {
        $this->farmerUser->forceFill(['ussd_pin_hash' => Hash::make('4829')])->save();
        ($this->dial)('', true);
    });

    test('the right PIN shows the menu and 0 ends the session', function () {
        $menu = ($this->dial)('4829')->assertOk();
        expect($menu->json('continueSession'))->toBeTrue()
            ->and($menu->json('message'))->toContain('Kofi');

        expect(($this->dial)('0')->json('continueSession'))->toBeFalse();
    });

    test('a wrong PIN is counted and the right one clears the count', function () {
        ($this->dial)('1357');
        expect($this->farmerUser->fresh()->ussd_pin_attempts)->toBe(1);

        ($this->dial)('4829');
        expect($this->farmerUser->fresh()->ussd_pin_attempts)->toBe(0);
    });

    test('five wrong PINs lock it for 30 minutes, even for the right PIN, then it opens again', function () {
        foreach (range(1, 5) as $try) {
            $last = ($this->dial)('1357');
        }

        expect($last->json('continueSession'))->toBeFalse();

        ($this->dial)('', true);
        expect(($this->dial)('4829')->json('continueSession'))->toBeFalse();

        $this->travel(31)->minutes();
        ($this->dial)('', true);

        expect(($this->dial)('4829')->json('continueSession'))->toBeTrue()
            ->and($this->farmerUser->fresh()->ussd_pin_attempts)->toBe(0);
    });
});
