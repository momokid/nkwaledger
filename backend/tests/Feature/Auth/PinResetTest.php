<?php

use App\Contracts\SmsProvider;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->userA = User::factory()->create(['phone' => '0244000501']);
    $this->userA->assignRole('farmer');
    $this->userB = User::factory()->create(['phone' => '0244000502']);
    $this->userB->assignRole('farmer');
});

function pinCodeSentTo(string $phone): ?string
{
    $message = collect(app(SmsProvider::class)->sent)->where('phone', $phone)->last()['message'] ?? '';

    return preg_match('/code is: (\d{6})/', $message, $found) ? $found[1] : null;
}

function pinSmsCount(string $phone): int
{
    return collect(app(SmsProvider::class)->sent)->where('phone', $phone)->count();
}

function askForPinCode(User $user)
{
    return test()->actingAs($user)->postJson('/pin-reset/send');
}

function sendPinCode(User $user, string $code)
{
    return test()->actingAs($user)->postJson('/pin-reset/confirm', ['code' => $code]);
}

test('asking for a code stores a hashed pin_reset code and texts the plain one to the phone on record', function () {
    askForPinCode($this->userA)->assertOk();

    $code = pinCodeSentTo('0244000501');
    $stored = OtpCode::where('identifier', '0244000501')->where('type', 'pin_reset')->first();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($stored->code)->not->toBe($code)
        ->and(Hash::check($code, $stored->code))->toBeTrue()
        ->and($stored->expires_at->diffInMinutes(now(), true))->toBeLessThanOrEqual(5);
});

test('the right code is accepted once and cannot be used again', function () {
    askForPinCode($this->userA);
    $code = pinCodeSentTo('0244000501');

    sendPinCode($this->userA, $code)->assertOk()->assertJson(['status' => 'ok']);
    sendPinCode($this->userA, $code)->assertStatus(422)->assertJson(['status' => 'expired']);
});

test('a wrong code counts toward the limit, and after the limit even the right code is refused', function () {
    askForPinCode($this->userA);
    $code = pinCodeSentTo('0244000501');
    $wrong = $code === '123456' ? '654321' : '123456';

    for ($i = 0; $i < 3; $i++) {
        sendPinCode($this->userA, $wrong)->assertStatus(422)->assertJson(['status' => 'wrong']);
    }

    expect(OtpCode::where('type', 'pin_reset')->first()->attempts)->toBe(3);

    sendPinCode($this->userA, $code)->assertStatus(422)->assertJson(['status' => 'too_many']);
});

test('an expired code is refused', function () {
    askForPinCode($this->userA);
    $code = pinCodeSentTo('0244000501');

    $this->travel(6)->minutes();

    sendPinCode($this->userA, $code)->assertStatus(422)->assertJson(['status' => 'expired']);
});

test('a second request inside the cooldown sends no second text, and one after it does', function () {
    askForPinCode($this->userA)->assertOk();
    askForPinCode($this->userA)->assertOk();

    expect(pinSmsCount('0244000501'))->toBe(1);

    $this->travel(61)->seconds();
    askForPinCode($this->userA)->assertOk();

    expect(pinSmsCount('0244000501'))->toBe(2);
});

test('the throttle stops a seventh request in an hour', function () {
    for ($i = 0; $i < 6; $i++) {
        askForPinCode($this->userA)->assertOk();
        $this->travel(61)->seconds();
    }

    askForPinCode($this->userA)->assertStatus(429);
});

test('every request and every successful reset is audit logged without the code', function () {
    askForPinCode($this->userA);
    $code = pinCodeSentTo('0244000501');
    sendPinCode($this->userA, $code);

    $requested = AuditLog::where('action', 'pin.reset_requested')->where('user_id', $this->userA->id)->first();
    $reset = AuditLog::where('action', 'pin.reset')->where('user_id', $this->userA->id)->first();

    expect($requested)->not->toBeNull()
        ->and($requested->created_at)->not->toBeNull()
        ->and($reset)->not->toBeNull();

    $everything = AuditLog::all()->toJson();

    expect($everything)->not->toContain($code);
});

test('neither the code nor a pin is ever written to the logs', function () {
    $logged = [];
    Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged) {
        $logged[] = $event->message . json_encode($event->context);
    });

    askForPinCode($this->userA);
    $code = pinCodeSentTo('0244000501');
    sendPinCode($this->userA, '000999');
    sendPinCode($this->userA, $code);

    expect(implode(' ', $logged))->not->toContain($code)->not->toContain('000999');
});

test('another user\'s code does not work, and does not use up theirs', function () {
    askForPinCode($this->userB);
    $theirs = pinCodeSentTo('0244000502');

    sendPinCode($this->userA, $theirs)->assertStatus(422);
    sendPinCode($this->userB, $theirs)->assertOk();
});

test('a request with no session is refused', function () {
    $this->postJson('/pin-reset/send')->assertUnauthorized();
    $this->postJson('/pin-reset/confirm', ['code' => '123456'])->assertUnauthorized();
});

test('the answers never carry a phone number', function () {
    $sent = askForPinCode($this->userA)->assertOk()->getContent();
    $refused = sendPinCode($this->userA, '111111')->getContent();

    expect($sent . $refused)->not->toContain('0244000501');
});
