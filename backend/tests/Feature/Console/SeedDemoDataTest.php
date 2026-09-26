<?php

use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\Transaction;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RegionDistrictSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(RegionDistrictSeeder::class);
});

test('demo:seed creates the specified role counts', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    $demoUsers = User::where('email', 'like', '%@demo.nkwaledger.test')->get();

    expect($demoUsers->filter(fn($u) => $u->hasRole('admin'))->count())->toBe(2)
        ->and($demoUsers->filter(fn($u) => $u->hasRole('agent'))->count())->toBe(3)
        ->and($demoUsers->filter(fn($u) => $u->hasRole('vet'))->count())->toBe(3)
        ->and($demoUsers->filter(fn($u) => $u->hasRole('adviser'))->count())->toBe(3)
        ->and($demoUsers->count())->toBeGreaterThanOrEqual(20)
        ->and($demoUsers->count())->toBeLessThanOrEqual(25);
});

test('demo:seed gives every farmer at least one produce sale and one expense transaction', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    $profiles = FarmerProfile::whereHas(
        'user',
        fn($q) => $q->where('email', 'like', '%@demo.nkwaledger.test'),
    )->get();

    expect($profiles)->not->toBeEmpty();

    foreach ($profiles as $profile) {
        $hasProduceSale = Transaction::where('farmer_profile_id', $profile->id)
            ->whereHas('template', fn($q) => $q->where('is_produce_sale', true))
            ->exists();

        $hasExpense = Transaction::where('farmer_profile_id', $profile->id)
            ->where('transaction_type', Transaction::EXPENSE)
            ->exists();

        expect($hasProduceSale)->toBeTrue("farmer profile {$profile->id} has no produce-sale transaction");
        expect($hasExpense)->toBeTrue("farmer profile {$profile->id} has no expense transaction");
    }
});

test('demo:seed gives every kiosk at least one kiosk product with a price', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    $kiosks = Kiosk::whereHas('supplier.user', fn($q) => $q->where('email', 'like', '%@demo.nkwaledger.test'))
        ->with('kioskProducts')
        ->get();

    expect($kiosks)->not->toBeEmpty();

    foreach ($kiosks as $kiosk) {
        expect($kiosk->kioskProducts)->not->toBeEmpty();
        expect($kiosk->kioskProducts->first()->price)->not->toBeNull();
    }
});

test('demo:seed --fresh-only wipes demo data and does not reseed', function () {
    $this->artisan('demo:seed')->assertExitCode(0);

    expect(User::where('email', 'like', '%@demo.nkwaledger.test')->count())->toBeGreaterThan(0);

    $this->artisan('demo:seed --fresh-only')->assertExitCode(0);

    expect(User::where('email', 'like', '%@demo.nkwaledger.test')->count())->toBe(0)
        ->and(FarmerProfile::count())->toBe(0)
        ->and(Kiosk::count())->toBe(0);
});
