<?php

use App\Models\User;
use App\Models\UserKnownDevice;
use App\Services\LoginAnomalyService;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->service = app(LoginAnomalyService::class);
});

function anomalyRequest(string $ip = '102.176.65.10', string $userAgent = 'Mozilla/5.0 Test Browser'): Request
{
    return Request::create('/login', 'POST', server: [
        'REMOTE_ADDR'     => $ip,
        'HTTP_USER_AGENT' => $userAgent,
    ]);
}

test('an agent on an unrecognised device must complete otp', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    expect($this->service->requiresOtp($agent, anomalyRequest()))->toBeTrue();
});

test('an agent on a recognised device does not need otp', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->service->checkAndRecord($agent, anomalyRequest());

    expect($this->service->requiresOtp($agent, anomalyRequest()))->toBeFalse();
});

test('vet, adviser and supplier all need otp on an unrecognised device', function () {
    foreach (['vet', 'adviser', 'supplier'] as $role) {
        $user = User::factory()->create();
        $user->assignRole($role);

        expect($this->service->requiresOtp($user, anomalyRequest()))->toBeTrue();
    }
});

test('a farmer never needs otp, even on an unrecognised device', function () {
    $farmer = User::factory()->create();
    $farmer->assignRole('farmer');

    expect($this->service->requiresOtp($farmer, anomalyRequest()))->toBeFalse();
});

test('asking whether otp is required does not record the device', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    $this->service->requiresOtp($agent, anomalyRequest());

    expect(UserKnownDevice::where('user_id', $agent->id)->count())->toBe(0);
});

test('a device belonging to one user does not count as known for another', function () {
    $first = User::factory()->create();
    $first->assignRole('agent');
    $second = User::factory()->create();
    $second->assignRole('agent');

    $this->service->checkAndRecord($first, anomalyRequest());

    expect($this->service->requiresOtp($second, anomalyRequest()))->toBeTrue();
});
