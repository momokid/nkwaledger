<?php

// USSD PIN changes: a farmer changes their own PIN, asks their agent when they forget it, and the agent sets a temporary
// PIN that the farmer must replace at once.

require_once __DIR__ . '/../../Support/LoanWorld.php';

use App\Contracts\SmsProvider;
use App\Models\AuditLog;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    buildLoanWorld($this);
    config(['services.ussd.secret' => 'test-secret']);

    $this->farmerUser->forceFill(['phone' => '0244445566', 'phone_verified_at' => now(), 'ussd_pin_hash' => Hash::make('4829')])->save();

    $this->dial = fn(string $input = '', bool $new = false, string $session = 's-1') => $this->postJson('/ussd/callback/test-secret', [
        'sessionID' => $session, 'msisdn' => '233244445566', 'newSession' => $new, 'userData' => $input,
    ]);
    $this->walk = function (string ...$inputs) {
        ($this->dial)('', true);
        $reply = ($this->dial)('4829');

        foreach ($inputs as $input) {
            $reply = ($this->dial)($input);
        }

        return $reply;
    };
    $this->resetUrl = "/agent/farmers/{$this->profile->uuid}/ussd-pin";
    $this->asked = fn() => Notification::where('user_id', $this->agent->id)->where('kind', 'ussd.pin_request')->get();
    $this->pinHash = fn() => $this->farmerUser->fresh()->ussd_pin_hash;
});

// --- the farmer changes their own PIN ---

test('the menu offers Change PIN', function () {
    expect(($this->walk)()->json('message'))->toContain('7. Change PIN');
});

test('Change PIN asks for the current PIN first, and a wrong one is counted', function () {
    $ask = ($this->walk)('7');
    expect($ask->json('message'))->toContain('current PIN')->toContain('0. Back');

    $wrong = ($this->dial)('1357');

    expect($wrong->json('continueSession'))->toBeTrue()->and($wrong->json('message'))->toContain('current PIN')
        ->and($this->farmerUser->fresh()->ussd_pin_attempts)->toBe(1);
});

test('the new PIN cannot be the old one, or a weak one', function () {
    ($this->walk)('7', '4829');

    $same = ($this->dial)('4829');
    expect($same->json('message'))->toContain('same as')->and(($this->pinHash)())->not->toBeNull();

    $weak = ($this->dial)('1111');
    expect($weak->json('message'))->toContain('not all the same');
});

test('a new PIN is confirmed, saved and used from the next dial', function () {
    ($this->walk)('7', '4829', '7352');
    $done = ($this->dial)('7352');

    expect($done->json('continueSession'))->toBeFalse()->and($done->json('message'))->toContain('changed')
        ->and(Hash::check('7352', ($this->pinHash)()))->toBeTrue();

    ($this->dial)('', true, 's-2');
    expect(($this->dial)('7352', false, 's-2')->json('message'))->toContain('Hello');
});

test('two new PINs that differ are not saved', function () {
    ($this->walk)('7', '4829', '7352');
    ($this->dial)('7353');

    expect(Hash::check('4829', ($this->pinHash)()))->toBeTrue();
});

test('0 on the new-PIN screen goes back to the menu', function () {
    ($this->walk)('7', '4829');

    expect(($this->dial)('0')->json('message'))->toContain('1. Summary');
});

// --- the farmer forgot the PIN ---

test('the PIN screens offer Forgot PIN, and choosing it asks the agent', function () {
    $screen = ($this->dial)('', true);
    expect($screen->json('message'))->toContain('0. Forgot PIN');

    $sent = ($this->dial)('0');

    expect($sent->json('continueSession'))->toBeFalse()->and($sent->json('message'))->toContain('agent')
        ->and(($this->asked)())->toHaveCount(1)
        ->and(($this->asked)()->first()->link)->toBe("/agent/farmers/{$this->profile->uuid}")
        ->and(($this->asked)()->first()->message)->toContain($this->farmerUser->surname)
        ->and(AuditLog::where('action', 'ussd_pin.requested')->count())->toBe(1);
});

test('asking twice in an hour tells the farmer to wait and does not tell the agent twice', function () {
    ($this->dial)('', true);
    ($this->dial)('0');

    ($this->dial)('', true, 's-2');
    $again = ($this->dial)('0', false, 's-2');

    expect($again->json('message'))->toContain('already')->and(($this->asked)())->toHaveCount(1);
});

test('a farmer with no agent is told to contact support', function () {
    $this->profile->forceFill(['assigned_agent_id' => null])->save();

    ($this->dial)('', true);
    $reply = ($this->dial)('0');

    expect($reply->json('message'))->toContain('support')->and(($this->asked)())->toHaveCount(0);
});

// --- the agent sets a temporary PIN ---

test('the assigned agent sets a temporary PIN, which is hashed, marked for change, logged, and sent as a notice without the PIN', function () {
    $this->farmerUser->forceFill(['ussd_pin_attempts' => 5, 'ussd_pin_locked_at' => now()])->save();

    $response = $this->actingAs($this->agent)->postJson($this->resetUrl)->assertOk();
    $pin = $response->json('pin');
    $farmer = $this->farmerUser->fresh();

    expect($pin)->toMatch('/^\d{4}$/')
        ->and(Hash::check($pin, $farmer->ussd_pin_hash))->toBeTrue()
        ->and([$farmer->ussd_pin_must_change, $farmer->ussd_pin_attempts, $farmer->ussd_pin_locked_at])->toBe([true, 0, null]);

    $audit = AuditLog::where('action', 'ussd_pin.reset')->firstOrFail();
    $sms = collect(app(SmsProvider::class)->sent)->last();

    expect(json_encode($audit->toArray()))->not->toContain($pin)
        ->and($sms['phone'])->toBe('0244445566')
        ->and($sms['message'])->not->toContain($pin)->toContain('PIN');
});

test('only the farmer\'s own agent can set it', function () {
    $this->actingAs($this->otherAgent)->postJson($this->resetUrl)->assertNotFound();
    $this->actingAs($this->farmerUser)->postJson($this->resetUrl)->assertForbidden();
    $this->actingAs($this->admin)->postJson($this->resetUrl)->assertForbidden();

    expect($this->farmerUser->fresh()->ussd_pin_must_change)->toBeFalse();
});

test('after a temporary PIN the farmer must choose a new one before anything else', function () {
    $pin = $this->actingAs($this->agent)->postJson($this->resetUrl)->json('pin');

    ($this->dial)('', true);
    $forced = ($this->dial)($pin);

    expect($forced->json('message'))->toContain('new PIN')->not->toContain('0. Back');

    // the temporary PIN is not allowed again, and the menu is not reachable yet
    expect(($this->dial)($pin)->json('message'))->toContain('same as');
    ($this->dial)('6');
    expect(($this->dial)('7352')->json('message'))->toContain('again');

    $menu = ($this->dial)('7352');

    expect($menu->json('message'))->toContain('Hello')
        ->and($this->farmerUser->fresh()->ussd_pin_must_change)->toBeFalse()
        ->and(Hash::check('7352', ($this->pinHash)()))->toBeTrue();
});

test('the farmer page offers the reset to the assigned agent only', function () {
    $flag = fn($user, string $url) => $this->actingAs($user)->get($url)->assertOk()->viewData('page')['props']['ussd_pin_reset'];

    expect($flag($this->agent, "/agent/farmers/{$this->profile->uuid}"))->toBeTrue()
        ->and($flag($this->admin, "/admin/farmers/{$this->profile->uuid}"))->toBeFalse();
});
