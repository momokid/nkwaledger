<?php

// USSD menu item 5: a farmer picks English or Twi, it is remembered, and every screen follows it.

use App\Models\FarmerProfile;
use App\Models\User;
use App\Services\Ussd\UssdText;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser = User::factory()->create(['phone' => '0244445566', 'phone_verified_at' => now(), 'first_name' => 'Kofi', 'ussd_pin_hash' => Hash::make('4829')]);
    $this->farmerUser->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);

    $this->dial = fn(string $input = '', bool $new = false, string $session = 's-1', string $phone = '233244445566') => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => $session, 'msisdn' => $phone, 'newSession' => $new, 'userData' => $input,
    ]);
    $this->signIn = function (string $session = 's-1') {
        ($this->dial)('', true, $session);

        return ($this->dial)('4829', false, $session);
    };
});

test('the menu offers Choose Language', function () {
    expect(($this->signIn)()->json('message'))->toContain('5. Choose Language');
});

test('choosing Twi is saved and the next dial speaks Twi', function () {
    ($this->signIn)();
    expect(($this->dial)('5')->json('message'))->toContain('1. English')->toContain('2. Twi');

    $saved = ($this->dial)('2');

    expect($saved->json('continueSession'))->toBeFalse()
        ->and($this->farmerUser->fresh()->ussd_language)->toBe('tw');

    $next = ($this->dial)('', true, 's-2');

    expect($next->json('message'))->toBe(app(UssdText::class)->in('tw')->get('enter_pin'))
        ->and($next->json('message'))->not->toBe(app(UssdText::class)->in('en')->get('enter_pin'));
});

test('choosing English goes back', function () {
    $this->farmerUser->forceFill(['ussd_language' => 'tw'])->save();
    ($this->signIn)();
    ($this->dial)('5');
    ($this->dial)('1');

    expect($this->farmerUser->fresh()->ussd_language)->toBeNull()
        ->and(($this->dial)('', true, 's-2')->json('message'))->toBe(app(UssdText::class)->in('en')->get('enter_pin'));
});

test('an answer that is not 1 or 2 asks again', function () {
    ($this->signIn)();
    ($this->dial)('5');

    $again = ($this->dial)('9');

    expect($again->json('continueSession'))->toBeTrue()->and($again->json('message'))->toContain('2. Twi');
});

test('a number that is not a farmer always gets English', function () {
    $reply = ($this->dial)('', true, 's-9', '233279998877');

    expect($reply->json('message'))->toBe(app(UssdText::class)->in('en')->get('not_registered'));
});

test('every English screen has a Twi one', function () {
    $en = json_decode(file_get_contents(resource_path('ussd.en.json')), true);
    $tw = json_decode(file_get_contents(resource_path('ussd.tw.json')), true);

    expect(array_keys($tw))->toBe(array_keys($en));
});

test('0 on the language screen goes back to the menu, and the language is not changed', function () {
    ($this->signIn)();
    expect(($this->dial)('5')->json('message'))->toContain('0. Back');

    expect(($this->dial)('0')->json('message'))->toContain('5. Choose Language')
        ->and($this->farmerUser->fresh()->ussd_language)->toBeNull();
});

test('the back line follows the farmer\'s language', function () {
    $this->farmerUser->forceFill(['ussd_language' => 'tw'])->save();
    ($this->signIn)();

    expect(($this->dial)('5')->json('message'))->toContain(app(UssdText::class)->in('tw')->get('back'));
});
