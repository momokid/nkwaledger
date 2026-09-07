<?php

use App\Models\District;
use App\Models\Region;
use Database\Seeders\RegionDistrictSeeder;

test('seeds all 16 official regions', function () {
    $this->seed(RegionDistrictSeeder::class);

    expect(Region::count())->toBe(16);
});

test('seeds all 261 official districts', function () {
    $this->seed(RegionDistrictSeeder::class);

    expect(District::count())->toBe(261);
});

test('running it twice does not create duplicates', function () {
    $this->seed(RegionDistrictSeeder::class);
    $this->seed(RegionDistrictSeeder::class);

    expect(Region::count())->toBe(16);
    expect(District::count())->toBe(261);
});
