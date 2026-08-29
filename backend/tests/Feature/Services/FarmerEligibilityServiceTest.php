<?php

use App\Enums\IdentityType;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\User;
use App\Services\FarmerEligibilityService;

beforeEach(function () {
    $this->service = app(FarmerEligibilityService::class);
});

function eligibleFarmer(array $profileState = []): User
{
    $user = User::factory()->create();
    $user->assignRole('farmer');

    $profile = FarmerProfile::factory()->create(array_merge(['user_id' => $user->id], $profileState));
    $profile->farmTypes()->attach(FarmType::factory()->withCategory()->create()->id);

    return $user->fresh();
}

test('a farmer with a verified phone and a farm type can transact', function () {
    expect($this->service->canTransact(eligibleFarmer()))->toBeTrue();
});

test('a farmer with no profile cannot transact', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    expect($this->service->canTransact($user))->toBeFalse();
});

test('a farmer with an unverified phone cannot transact', function () {
    $user = eligibleFarmer();
    $user->forceFill(['phone_verified_at' => null])->save();

    expect($this->service->canTransact($user->fresh()))->toBeFalse();
});

test('a farmer with no farm type cannot transact', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $user->id]);

    expect($this->service->canTransact($user->fresh()))->toBeFalse();
});

test('a farmer on an inactive profile cannot transact', function () {
    expect($this->service->canTransact(eligibleFarmer(['is_active' => false])))->toBeFalse();
});

test('a farmer on a disabled account cannot transact', function () {
    $user = eligibleFarmer();
    $user->forceFill(['is_active' => false])->save();

    expect($this->service->canTransact($user->fresh()))->toBeFalse();
});

test('an identity document is not required to transact', function () {
    $user = eligibleFarmer(['identity_type' => null, 'identity_number_hash' => null]);

    expect($this->service->canTransact($user))->toBeTrue();
});

test('a soft deleted profile cannot transact', function () {
    $user = eligibleFarmer();
    $user->farmerProfile->delete();

    expect($this->service->canTransact($user->fresh()))->toBeFalse();
});

test('a farmer who can transact has no blocking reason', function () {
    expect($this->service->reasonCannotTransact(eligibleFarmer()))->toBeNull();
});

test('a farmer with no profile is told to finish setting up', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');

    expect($this->service->reasonCannotTransact($user))->toBeString();
});

test('a farmer with an unverified phone is told to confirm their number', function () {
    $user = eligibleFarmer();
    $user->forceFill(['phone_verified_at' => null])->save();

    expect($this->service->reasonCannotTransact($user->fresh()))->toContain('phone');
});

test('a farmer with no farm type is told what is missing', function () {
    $user = User::factory()->create();
    $user->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $user->id]);

    expect($this->service->reasonCannotTransact($user->fresh()))->toContain('farm');
});

test('a farmer without a verified identity document cannot be credit scored', function () {
    expect($this->service->canBeCreditScored(eligibleFarmer()))->toBeFalse();
});

test('a farmer with an unverified document on file still cannot be credit scored', function () {
    $user = eligibleFarmer([
        'identity_type' => IdentityType::GhanaCard,
        'identity_number_hash' => str_repeat('a', 64),
        'identity_verified_at' => null,
    ]);

    expect($this->service->canBeCreditScored($user))->toBeFalse();
});

test('a farmer with a verified document can be credit scored', function () {
    $user = eligibleFarmer([
        'identity_type' => IdentityType::GhanaCard,
        'identity_number_hash' => str_repeat('b', 64),
        'identity_verified_at' => now(),
    ]);

    expect($this->service->canBeCreditScored($user))->toBeTrue();
});

test('a farmer who cannot transact can never be credit scored', function () {
    $user = eligibleFarmer([
        'is_active' => false,
        'identity_type' => IdentityType::GhanaCard,
        'identity_number_hash' => str_repeat('c', 64),
        'identity_verified_at' => now(),
    ]);

    expect($this->service->canBeCreditScored($user))->toBeFalse();
});

test('a non farmer cannot transact as a farmer', function () {
    $agent = User::factory()->create();
    $agent->assignRole('agent');

    expect($this->service->canTransact($agent))->toBeFalse();
});
