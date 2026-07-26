<?php

use App\Models\Community;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\QueryException;

test('a district can be created under a region', function () {
    $region = Region::create(['name' => 'Northern']);

    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);

    expect($district->name)->toBe('Tamale');
    expect($district->region->id)->toBe($region->id);
});

test('district name must be unique within its region', function () {
    $region = Region::create(['name' => 'Northern']);
    District::create(['name' => 'Central', 'region_id' => $region->id]);

    expect(fn() => District::create(['name' => 'Central', 'region_id' => $region->id]))
        ->toThrow(QueryException::class);
});

test('two different regions can each have a district with the same name', function () {
    $regionOne = Region::create(['name' => 'Northern']);
    $regionTwo = Region::create(['name' => 'Ashanti']);

    District::create(['name' => 'Central', 'region_id' => $regionOne->id]);
    $second = District::create(['name' => 'Central', 'region_id' => $regionTwo->id]);

    expect($second->exists)->toBeTrue();
});

test('a district can be soft deleted', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Yendi', 'region_id' => $region->id]);

    $district->delete();

    expect(District::find($district->id))->toBeNull();
    expect(District::withTrashed()->find($district->id))->not->toBeNull();
});

test('soft deleting a district cascades to its communities', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Savelugu', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Diare', 'district_id' => $district->id]);

    $district->delete();

    expect(Community::find($community->id))->toBeNull();
    expect(Community::withTrashed()->find($community->id))->not->toBeNull();
});
