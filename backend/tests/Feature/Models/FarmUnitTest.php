<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

test('a unit belongs to a farmer profile', function () {
    $profile = FarmerProfile::factory()->create();
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $profile->id]);

    expect($unit->farmerProfile->id)->toBe($profile->id);
});

test('a farmer can have many units', function () {
    $profile = FarmerProfile::factory()->create();
    FarmUnit::factory()->count(3)->create(['farmer_profile_id' => $profile->id]);

    expect($profile->fresh()->farmUnits)->toHaveCount(3);
});

test('a unit belongs to a farm type', function () {
    $farmType = FarmType::factory()->withCategory()->create();
    $unit = FarmUnit::factory()->create(['farm_type_id' => $farmType->id]);

    expect($unit->farmType->id)->toBe($farmType->id);
});

// weather follows the land, so a unit carries its own community rather than the farmer's
test('a unit belongs to a community', function () {
    $community = Community::factory()->create();
    $unit = FarmUnit::factory()->create(['community_id' => $community->id]);

    expect($unit->community->id)->toBe($community->id);
});

test('a unit can sit in a different community from the farmer', function () {
    $profile = FarmerProfile::factory()->create();
    $elsewhere = Community::factory()->create();

    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $profile->id,
        'community_id' => $elsewhere->id,
    ]);

    expect($unit->community_id)->not->toBe($profile->community_id);
});

test('two units on one farm cannot share a name', function () {
    $profile = FarmerProfile::factory()->create();
    FarmUnit::factory()->create(['farmer_profile_id' => $profile->id, 'name' => 'Pen A']);

    FarmUnit::factory()->create(['farmer_profile_id' => $profile->id, 'name' => 'Pen A']);
})->throws(UniqueConstraintViolationException::class);

test('two farmers may each have a unit of the same name', function () {
    FarmUnit::factory()->create(['name' => 'Pen A']);
    $second = FarmUnit::factory()->create(['name' => 'Pen A']);

    expect($second->exists)->toBeTrue();
});

test('a unit records who created it', function () {
    $agent = User::factory()->create();
    $unit = FarmUnit::factory()->create(['created_by' => $agent->id]);

    expect($unit->createdBy->id)->toBe($agent->id);
});

test('a new unit is not approved', function () {
    expect(FarmUnit::factory()->create()->isApproved())->toBeFalse();
});

test('an approved unit reports itself approved', function () {
    expect(FarmUnit::factory()->approved()->create()->isApproved())->toBeTrue();
});

test('an approved unit records who approved it and when', function () {
    $unit = FarmUnit::factory()->approved()->create();

    expect($unit->approved_by)->not->toBeNull()
        ->and($unit->approved_at)->not->toBeNull();
});

// entries are blessed on the strength of an approval, so it cannot be taken back
test('an approved unit cannot be unapproved', function () {
    $unit = FarmUnit::factory()->approved()->create();

    $unit->update(['approved_at' => null, 'approved_by' => null]);

    expect($unit->fresh()->approved_at)->not->toBeNull();
});

// the agent who set up a pen is not the one who vouches that it exists
test('the creator is the conflicted party for approval', function () {
    $agent = User::factory()->create();
    $unit = FarmUnit::factory()->create(['created_by' => $agent->id]);

    expect($unit->conflictedUserId())->toBe($agent->id);
});

test('a unit can hold a capacity', function () {
    $unit = FarmUnit::factory()->create(['capacity' => 250, 'capacity_unit' => 'birds']);

    expect($unit->capacity)->toBe('250.00')
        ->and($unit->capacity_unit)->toBe('birds');
});

test('a unit without a capacity is allowed', function () {
    expect(FarmUnit::factory()->create(['capacity' => null])->capacity)->toBeNull();
});

test('is_active reads true on an unsaved instance', function () {
    expect((new FarmUnit())->is_active)->toBeTrue();
});

test('a deleted unit is soft deleted', function () {
    $unit = FarmUnit::factory()->create();

    $unit->delete();

    expect(FarmUnit::find($unit->id))->toBeNull()
        ->and(FarmUnit::withTrashed()->find($unit->id))->not->toBeNull();
});

test('the approved scope returns only approved units', function () {
    FarmUnit::factory()->count(2)->create();
    FarmUnit::factory()->approved()->create();

    expect(FarmUnit::approved()->count())->toBe(1);
});
