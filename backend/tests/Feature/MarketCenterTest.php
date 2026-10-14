<?php

use App\Enums\KioskProductStatus;
use App\Models\CatalogProduct;
use App\Models\Community;
use App\Models\District;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\MarketplaceCategory;
use App\Models\ProduceListing;
use App\Models\Supplier;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->farmer = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function makeMarketKiosk(FarmerProfile $farmer, array $productOverrides = []): array
{
    $district = District::factory()->create();
    $farmer->update(['community_id' => Community::factory()->create(['district_id' => $district->id])->id]);

    $user = User::factory()->create();
    $supplier = Supplier::factory()->create(['user_id' => $user->id]);
    $kiosk = Kiosk::factory()->confirmed()->create([
        'supplier_id' => $supplier->id,
        'district_id' => $district->id,
        'region_id' => $district->region_id,
    ]);

    $catalogProduct = CatalogProduct::factory()->create(['name' => 'NPK Fertilizer']);
    $product = KioskProduct::factory()->priceConfirmed()->create([
        'kiosk_id' => $kiosk->id,
        'catalog_product_id' => $catalogProduct->id,
        'in_stock' => true,
        'status' => KioskProductStatus::Active,
        ...$productOverrides,
    ]);

    return [$kiosk, $product];
}

function makeMarketListing(FarmerProfile $farmer): ProduceListing
{
    $farmType = FarmType::where('name', 'Maize')->firstOrFail();
    $unit = FarmUnit::factory()->approved()->create(['farm_type_id' => $farmType->id, 'farmer_profile_id' => $farmer->id]);
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 20, 'current_quantity' => 20]);

    return ProduceListing::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'farmer_profile_id' => $farmer->id,
    ]);
}

test('a category row only shows items assigned to it, up to its display_count', function () {
    $category = MarketplaceCategory::factory()->create(['display_count' => 1]);

    [, $productA] = makeMarketKiosk($this->farmer);
    $productA->marketplaceCategories()->attach($category->id);

    [, $productB] = makeMarketKiosk($this->farmer);
    $productB->marketplaceCategories()->attach($category->id);

    $otherCategory = MarketplaceCategory::factory()->create();
    [, $uncategorized] = makeMarketKiosk($this->farmer);
    $uncategorized->marketplaceCategories()->attach($otherCategory->id);

    $this->actingAs($this->farmerUser)->get('/market-center')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MarketCenter/Index')
            ->where('rows', fn($rows) => collect($rows)->firstWhere('category.slug', $category->slug)['items'] !== null
                && count(collect($rows)->firstWhere('category.slug', $category->slug)['items']) === 1
                && collect($rows)->firstWhere('category.slug', $category->slug)['has_more'] === true));
});

test('browse more paginates a single category, server-side, at its own display_count', function () {
    $category = MarketplaceCategory::factory()->create(['display_count' => 2]);

    foreach (range(1, 5) as $i) {
        [, $product] = makeMarketKiosk($this->farmer);
        $product->marketplaceCategories()->attach($category->id);
    }

    $this->actingAs($this->farmerUser)->get("/market-center/{$category->slug}?page=1")
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('MarketCenter/Category')
            ->where('total', 5)
            ->where('perPage', 2)
            ->where('lastPage', 3)
            ->where('items', fn($items) => count($items) === 2));

    $this->actingAs($this->farmerUser)->get("/market-center/{$category->slug}?page=3")
        ->assertInertia(fn($page) => $page->where('items', fn($items) => count($items) === 1));
});

test('search matches both kiosk products and produce listings, across categories', function () {
    [, $product] = makeMarketKiosk($this->farmer, ['catalog_product_id' => CatalogProduct::factory()->create(['name' => 'Zebra Feed'])->id]);
    $listing = makeMarketListing($this->farmer); // farm type "Maize"

    $this->actingAs($this->farmerUser)->get('/market-center?q=Zebra')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->where('query', 'Zebra')
            ->where('searchResults', fn($results) => count($results) === 1 && $results[0]['kind'] === 'kiosk_product'));

    $this->actingAs($this->farmerUser)->get('/market-center?q=Maize')
        ->assertInertia(fn($page) => $page
            ->where('searchResults', fn($results) => count($results) === 1 && $results[0]['kind'] === 'produce_listing'));
});

test('a produce listing with a real photo resolves to a real file', function () {
    Storage::fake('public');
    $listing = makeMarketListing($this->farmer);
    $listing->images()->create(['path' => 'produce-listings/sample.webp']);
    $category = MarketplaceCategory::factory()->create();
    $listing->marketplaceCategories()->attach($category->id);

    $this->actingAs($this->farmerUser)->get("/market-center/{$category->slug}")
        ->assertInertia(fn($page) => $page->where(
            'items.0.photo_url',
            fn($url) => str_contains($url, 'produce-listings/sample.webp'),
        ));
});

test('the old produce-listings browse destination redirects to Market Center', function () {
    $this->actingAs($this->farmerUser)->get('/produce-listings')->assertRedirect('/market-center');
});

test('vet and adviser can reach Market Center, which neither could before', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $this->actingAs($vet)->get('/market-center')->assertOk();

    $adviser = User::factory()->create();
    $adviser->assignRole('adviser');
    $this->actingAs($adviser)->get('/market-center')->assertOk();
});

test('the announcement banner is hidden when empty and shown once set', function () {
    $this->actingAs($this->farmerUser)->get('/market-center')
        ->assertInertia(fn($page) => $page->where('announcement', null));

    $admin = User::factory()->create();
    app(SettingsService::class)->set('marketplace.announcement_message', 'Market Day this Saturday!', $admin);

    $this->actingAs($this->farmerUser)->get('/market-center')
        ->assertInertia(fn($page) => $page->where('announcement.message', 'Market Day this Saturday!'));
});
