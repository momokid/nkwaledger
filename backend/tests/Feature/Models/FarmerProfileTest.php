<?php

use App\Enums\IdentityType;
use App\Models\Community;
use App\Models\FarmerGroup;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\User;
use App\Support\IdentityDocument;
use Illuminate\Database\UniqueConstraintViolationException;

test('a profile belongs to a user', function () {
    $user = User::factory()->create();
    $profile = FarmerProfile::factory()->create(['user_id' => $user->id]);

    expect($profile->user->id)->toBe($user->id);
});

test('a user can only have one profile', function () {
    $user = User::factory()->create();
    FarmerProfile::factory()->create(['user_id' => $user->id]);

    FarmerProfile::factory()->create(['user_id' => $user->id]);
})->throws(UniqueConstraintViolationException::class);

test('a profile belongs to a community', function () {
    $community = Community::factory()->create();
    $profile = FarmerProfile::factory()->create(['community_id' => $community->id]);

    expect($profile->community->id)->toBe($community->id);
});

test('region and district are reachable through the community', function () {
    $community = Community::factory()->create();
    $profile = FarmerProfile::factory()->create(['community_id' => $community->id]);

    expect($profile->community->district->id)->toBe($community->district_id)
        ->and($profile->community->district->region)->not->toBeNull();
});

test('a profile can belong to a farmer group', function () {
    $group = FarmerGroup::factory()->create();
    $profile = FarmerProfile::factory()->create(['farmer_group_id' => $group->id]);

    expect($profile->farmerGroup->id)->toBe($group->id);
});

test('a profile without a farmer group is allowed', function () {
    $profile = FarmerProfile::factory()->create(['farmer_group_id' => null]);

    expect($profile->farmerGroup)->toBeNull();
});

test('a profile has many farm types', function () {
    $profile = FarmerProfile::factory()->create();
    $poultry = FarmType::factory()->withCategory()->create();
    $maize = FarmType::factory()->withCategory()->create();

    $profile->farmTypes()->attach([$poultry->id, $maize->id]);

    expect($profile->fresh()->farmTypes)->toHaveCount(2);
});

test('the same farm type cannot be attached twice', function () {
    $profile = FarmerProfile::factory()->create();
    $poultry = FarmType::factory()->withCategory()->create();

    $profile->farmTypes()->attach($poultry->id);
    $profile->farmTypes()->attach($poultry->id);
})->throws(UniqueConstraintViolationException::class);

test('setting an identity number stores a hash and never the raw number', function () {
    $profile = FarmerProfile::factory()->create();

    $profile->identity_type = IdentityType::GhanaCard;
    $profile->identity_number = 'GHA-123456789-0';
    $profile->save();

    expect($profile->fresh()->identity_number_hash)
        ->not->toBeNull()
        ->not->toContain('123456789');
});

test('the same identity number always produces the same hash', function () {
    expect(IdentityDocument::hash('GHA-123456789-0'))->toBe(IdentityDocument::hash('GHA-123456789-0'));
});

test('formatting differences produce the same hash', function () {
    expect(IdentityDocument::hash(' gha 123456789 0 '))->toBe(IdentityDocument::hash('GHA-123456789-0'));
});

test('two farmers cannot register the same document', function () {
    FarmerProfile::factory()->create([
        'identity_type' => IdentityType::Passport,
        'identity_number_hash' => IdentityDocument::hash('G1234567'),
    ]);

    FarmerProfile::factory()->create([
        'identity_type' => IdentityType::Passport,
        'identity_number_hash' => IdentityDocument::hash('G1234567'),
    ]);
})->throws(UniqueConstraintViolationException::class);

test('the same number is allowed under a different document type', function () {
    FarmerProfile::factory()->create([
        'identity_type' => IdentityType::Passport,
        'identity_number_hash' => IdentityDocument::hash('1234567890'),
    ]);

    $second = FarmerProfile::factory()->create([
        'identity_type' => IdentityType::VoterId,
        'identity_number_hash' => IdentityDocument::hash('1234567890'),
    ]);

    expect($second->exists)->toBeTrue();
});

test('identity type casts to the enum', function () {
    $profile = FarmerProfile::factory()->create(['identity_type' => IdentityType::VoterId]);

    expect($profile->fresh()->identity_type)->toBe(IdentityType::VoterId);
});

test('a profile with no document yet is allowed', function () {
    $profile = FarmerProfile::factory()->create([
        'identity_type' => null,
        'identity_number_hash' => null,
    ]);

    expect($profile->identity_number_hash)->toBeNull();
});

test('is_active reads true on an unsaved instance', function () {
    expect((new FarmerProfile())->is_active)->toBeTrue();
});

test('dates cast to date and datetime instances', function () {
    $profile = FarmerProfile::factory()->create([
        'date_of_birth' => '1990-04-12',
        'onboarded_at' => now(),
    ]);

    expect($profile->date_of_birth)->toBeInstanceOf(Carbon\CarbonInterface::class)
        ->and($profile->onboarded_at)->toBeInstanceOf(Carbon\CarbonInterface::class);
});

test('a deleted profile is soft deleted', function () {
    $profile = FarmerProfile::factory()->create();

    $profile->delete();

    expect(FarmerProfile::find($profile->id))->toBeNull()
        ->and(FarmerProfile::withTrashed()->find($profile->id))->not->toBeNull();
});
