<?php

use App\Mail\LoginFromNewDeviceMail;
use App\Models\User;
use App\Models\UserKnownDevice;
use App\Services\LoginAnomalyService;
use App\Services\Sms\FakeSmsProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Contracts\SmsProvider;

beforeEach(function () {
    $this->service = app(LoginAnomalyService::class);
});

function fakeLoginRequest(string $ip = '102.176.65.10', string $userAgent = 'Mozilla/5.0 Test Browser'): Request
{
    return Request::create('/login', 'POST', server: [
        'REMOTE_ADDR' => $ip,
        'HTTP_USER_AGENT' => $userAgent,
    ]);
}

test('a first login from a new device alerts an admin by sms and email', function () {
    Mail::fake();

    $admin = User::factory()->create(['email' => 'admin@nkwaledger.com']);
    $admin->assignRole('admin');

    $this->service->checkAndRecord($admin, fakeLoginRequest());

    expect(app(SmsProvider::class)->sentTo($admin->phone))->toBeTrue();
    Mail::assertSent(LoginFromNewDeviceMail::class, fn($mail) => $mail->hasTo($admin->email));
});

test('a first login from a new device records the device', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->service->checkAndRecord($admin, fakeLoginRequest());

    expect(UserKnownDevice::where('user_id', $admin->id)->count())->toBe(1);
});

test('a second login from the same device does not send another alert', function () {
    Mail::fake();

    $admin = User::factory()->create(['email' => 'admin@nkwaledger.com']);
    $admin->assignRole('admin');

    $this->service->checkAndRecord($admin, fakeLoginRequest());
    app(FakeSmsProvider::class)->sent = [];

    $this->service->checkAndRecord($admin, fakeLoginRequest());

    expect(app(SmsProvider::class)->sentTo($admin->phone))->toBeTrue();
    Mail::assertSentCount(1);
});

test('a login from a different device sends a new alert', function () {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $this->service->checkAndRecord($admin, fakeLoginRequest(ip: '102.176.65.10'));
    $this->service->checkAndRecord($admin, fakeLoginRequest(ip: '41.66.20.5'));

    expect(UserKnownDevice::where('user_id', $admin->id)->count())->toBe(2);
});

test('an agent also gets alerted on a new device', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->service->checkAndRecord($agent, fakeLoginRequest());

    expect(app(SmsProvider::class)->sentTo($agent->phone))->toBeTrue();
});

test('a farmer does not get alerted or tracked', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    $this->service->checkAndRecord($farmer, fakeLoginRequest());

    expect(app(SmsProvider::class)->sentTo($farmer->phone))->toBeFalse();
    expect(UserKnownDevice::where('user_id', $farmer->id)->count())->toBe(0);
});

test('an admin with no email only gets the sms alert, no error', function () {
    Mail::fake();

    $admin = User::factory()->create(['email' => null]);
    $admin->assignRole('admin');

    $this->service->checkAndRecord($admin, fakeLoginRequest());

    expect(app(SmsProvider::class)->sentTo($admin->phone))->toBeTrue();
    Mail::assertNothingSent();
});
