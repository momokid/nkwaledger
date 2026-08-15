<?php

use App\Models\Community;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\QueryException;

test('a community can be created under a district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);

    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);

    expect($community->name)->toBe('Kalpohin');
    expect($community->district->id)->toBe($district->id);
});

test('community name must be unique within its district', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    Community::create(['name' => 'Central', 'district_id' => $district->id]);

    expect(fn() => Community::create(['name' => 'Central', 'district_id' => $district->id]))
        ->toThrow(QueryException::class);
});

test('two different districts can each have a community with the same name', function () {
    $region = Region::create(['name' => 'Northern']);
    $districtOne = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $districtTwo = District::create(['name' => 'Yendi', 'region_id' => $region->id]);

    Community::create(['name' => 'Central', 'district_id' => $districtOne->id]);
    $second = Community::create(['name' => 'Central', 'district_id' => $districtTwo->id]);

    expect($second->exists)->toBeTrue();
});

test('a community can be soft deleted', function () {
    $region = Region::create(['name' => 'Northern']);
    $district = District::create(['name' => 'Tamale', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Kalpohin', 'district_id' => $district->id]);

    $community->delete();

    expect(Community::find($community->id))->toBeNull();
    expect(Community::withTrashed()->find($community->id))->not->toBeNull();
});
