<?php

use App\Models\Community;
use App\Models\District;
use App\Models\Region;
use Illuminate\Database\QueryException;

test('a region can be created', function () {
    $region = Region::create(['name' => 'Northern']);

    expect($region->name)->toBe('Northern');
});

test('region name must be unique', function () {
    Region::create(['name' => 'Ashanti']);

    expect(fn() => Region::create(['name' => 'Ashanti']))
        ->toThrow(QueryException::class);
});

test('a region can be soft deleted', function () {
    $region = Region::create(['name' => 'Volta']);

    $region->delete();

    expect(Region::find($region->id))->toBeNull();
    expect(Region::withTrashed()->find($region->id))->not->toBeNull();
});

test('soft deleting a region cascades to its districts and their communities', function () {
    $region = Region::create(['name' => 'Bono']);
    $district = District::create(['name' => 'Sunyani', 'region_id' => $region->id]);
    $community = Community::create(['name' => 'Abesim', 'district_id' => $district->id]);

    $region->delete();

    expect(District::find($district->id))->toBeNull();
    expect(District::withTrashed()->find($district->id))->not->toBeNull();
    expect(Community::find($community->id))->toBeNull();
    expect(Community::withTrashed()->find($community->id))->not->toBeNull();
});
