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

test('choosing Twi saves it and shows the welcome menu in Twi at once', function () {
    ($this->signIn)();
    expect(($this->dial)('5')->json('message'))->toContain('1. English')->toContain('2. Twi');

    $menu = ($this->dial)('2');
    $twi = app(UssdText::class)->in('tw');

    expect($menu->json('continueSession'))->toBeTrue()
        ->and($menu->json('message'))->toBe($twi->get('menu', ['name' => 'Kofi']) . "\n" . $twi->get('menu_waiting'))
        ->and($this->farmerUser->fresh()->ussd_language)->toBe('tw');

    // the menu works in Twi, and the next dial remembers it
    expect(($this->dial)('6')->json('continueSession'))->toBeFalse();

    $next = ($this->dial)('', true, 's-2');

    expect($next->json('message'))->toStartWith($twi->get('enter_pin'))
        ->and($next->json('message'))->not->toStartWith(app(UssdText::class)->in('en')->get('enter_pin'));
});

test('choosing English goes back to the welcome menu in English', function () {
    $this->farmerUser->forceFill(['ussd_language' => 'tw'])->save();
    ($this->signIn)();
    ($this->dial)('5');

    $menu = ($this->dial)('1');

    expect($menu->json('continueSession'))->toBeTrue()
        ->and($menu->json('message'))->toContain('Hello Kofi.')
        ->and($this->farmerUser->fresh()->ussd_language)->toBeNull()
        ->and(($this->dial)('', true, 's-2')->json('message'))->toStartWith(app(UssdText::class)->in('en')->get('enter_pin'));
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
