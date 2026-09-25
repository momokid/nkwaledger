<?php

use App\Enums\KioskProductStatus;
use App\Enums\KioskStatus;
use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\ProductCategory;
use App\Models\Region;
use App\Models\Supplier;
use App\Models\User;
use App\Services\KioskSearchService;

beforeEach(function () {
    $this->service = app(KioskSearchService::class);
    $this->region = Region::factory()->create();
    $this->district = District::factory()->create(['region_id' => $this->region->id]);
    $this->community = Community::factory()->create(['district_id' => $this->district->id]);

    $this->farmer = FarmerProfile::factory()->create(['community_id' => $this->community->id]);
});

function makeKiosk(District $district, array $overrides = []): Kiosk
{
    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);

    return Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
        ...$overrides,
    ]);
}

function makeAvailableProduct(Kiosk $kiosk, ?ProductCategory $category = null): KioskProduct
{
    $catalogProduct = CatalogProduct::factory()->create([
        'category_id' => $category?->id,
    ]);

    return KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
    ]);
}

test('a kiosk with no in-stock unexpired products never appears', function () {
    $kiosk = makeKiosk($this->district);
    $product = makeAvailableProduct($kiosk);
    $product->update(['in_stock' => false]);

    $results = $this->service->rank($this->farmer);

    expect($results->pluck('uuid'))->not->toContain($kiosk->uuid);
});

test('an expired product does not count toward a kiosk having stock', function () {
    $kiosk = makeKiosk($this->district);
    $product = makeAvailableProduct($kiosk);
    $product->update(['expiry_date' => now()->subDay()->toDateString()]);

    $results = $this->service->rank($this->farmer);

    expect($results->pluck('uuid'))->not->toContain($kiosk->uuid);
});

test('a suspended kiosk never appears even with in-stock products', function () {
    $kiosk = makeKiosk($this->district, ['status' => KioskStatus::Suspended]);
    makeAvailableProduct($kiosk);

    $results = $this->service->rank($this->farmer);

    expect($results->pluck('uuid'))->not->toContain($kiosk->uuid);
});

test('a pending-confirmation kiosk never appears', function () {
    $kiosk = makeKiosk($this->district, ['status' => KioskStatus::PendingConfirmation, 'confirmed_at' => null]);
    makeAvailableProduct($kiosk);

    $results = $this->service->rank($this->farmer);

    expect($results->pluck('uuid'))->not->toContain($kiosk->uuid);
});

test('a kiosk with an active in-stock unexpired product appears', function () {
    $kiosk = makeKiosk($this->district);
    makeAvailableProduct($kiosk);

    $results = $this->service->rank($this->farmer);

    expect($results->pluck('uuid'))->toContain($kiosk->uuid);
});

test('the category filter narrows results to kiosks stocking that category', function () {
    $fertilizer = ProductCategory::factory()->create(['name' => 'Fertilizer']);
    $equipment = ProductCategory::factory()->create(['name' => 'Equipment']);

    $fertilizerKiosk = makeKiosk($this->district);
    makeAvailableProduct($fertilizerKiosk, $fertilizer);

    $equipmentKiosk = makeKiosk($this->district);
    makeAvailableProduct($equipmentKiosk, $equipment);

    $results = $this->service->rank($this->farmer, $fertilizer->id);

    expect($results->pluck('uuid'))
        ->toContain($fertilizerKiosk->uuid)
        ->not->toContain($equipmentKiosk->uuid);
});

test('a crop farmer sees a fertilizer kiosk boosted ahead of an equally-close equipment kiosk', function () {
    $cropCategory = FarmTypeCategory::create(['name' => 'Crop']);
    $maize = FarmType::create(['name' => 'Maize', 'category_id' => $cropCategory->id]);
    $this->farmer->farmTypes()->attach($maize->id);

    $fertilizer = ProductCategory::factory()->create(['name' => 'Fertilizer']);
    $equipment = ProductCategory::factory()->create(['name' => 'Equipment']);

    // both kiosks sit in the same district, so distance is identical for both
    $fertilizerKiosk = makeKiosk($this->district);
    makeAvailableProduct($fertilizerKiosk, $fertilizer);

    $equipmentKiosk = makeKiosk($this->district);
    makeAvailableProduct($equipmentKiosk, $equipment);

    $results = $this->service->rank($this->farmer);

    $uuids = $results->pluck('uuid')->values()->all();
    $fertilizerPosition = array_search($fertilizerKiosk->uuid, $uuids, true);
    $equipmentPosition = array_search($equipmentKiosk->uuid, $uuids, true);

    expect($fertilizerPosition)->toBeLessThan($equipmentPosition)
        ->and($results->firstWhere('uuid', $fertilizerKiosk->uuid)['matches_farm_type'])->toBeTrue()
        ->and($results->firstWhere('uuid', $equipmentKiosk->uuid)['matches_farm_type'])->toBeFalse();
});

test('the blended score lets a highly rated, well-selling kiosk outrank a purely closer one', function () {
    // constructed directly against the pure scoring function, since rating/sales are
    // hard-wired to 0 until Step 5 (orders/reviews) exists - this proves the weighting
    // itself, independent of that not-yet-built data source
    $veryClosePoorly = $this->service->blendedScore(proximityScore: 0.95, ratingAverage: null, salesCount: 0);
    $fartherButExcellent = $this->service->blendedScore(proximityScore: 0.6, ratingAverage: 5.0, salesCount: 50);

    expect($fartherButExcellent)->toBeGreaterThan($veryClosePoorly);
});

test('review and sales counts show an honest empty state when no orders/reviews exist yet', function () {
    $kiosk = makeKiosk($this->district);
    makeAvailableProduct($kiosk);

    $result = $this->service->rank($this->farmer)->firstWhere('uuid', $kiosk->uuid);

    expect($result['rating_average'])->toBeNull()
        ->and($result['review_count'])->toBe(0)
        ->and($result['sales_count'])->toBe(0);
});

test('a kiosk in the same district is ranked closer than one in a different region', function () {
    $sameDistrictKiosk = makeKiosk($this->district);
    makeAvailableProduct($sameDistrictKiosk);

    $otherRegion = Region::factory()->create();
    $otherDistrict = District::factory()->create(['region_id' => $otherRegion->id]);
    $farKiosk = makeKiosk($otherDistrict);
    makeAvailableProduct($farKiosk);

    $results = $this->service->rank($this->farmer);

    $uuids = $results->pluck('uuid')->values()->all();
    $nearPosition = array_search($sameDistrictKiosk->uuid, $uuids, true);
    $farPosition = array_search($farKiosk->uuid, $uuids, true);

    expect($nearPosition)->toBeLessThan($farPosition);
});
