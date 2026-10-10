<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\KioskProduct;
use App\Models\MarketplaceCategory;
use App\Models\ProduceListing;
use App\Models\ProduceListingImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

// the unified Market Center homepage - one ranked row per admin-managed
// MarketplaceCategory, kiosk products and produce listings mixed within a row on the
// same blendedScore() scale, plus a flat cross-content search. Replaces
// /my-marketplace and /produce-listings as separate browse destinations (Step 8)
class MarketCenterService
{
    public function __construct(private readonly KioskSearchService $kioskSearch) {}

    /** @return array<int, array{category: MarketplaceCategory, items: Collection, has_more: bool}> */
    public function homepage(?FarmerProfile $farmer): array
    {
        // computed once, reused by every category row below - the ranking itself
        // never changes per category, only which rows a category's items are drawn from
        $rankedKioskProducts = $this->kioskSearch->rankProducts($farmer);

        return MarketplaceCategory::active()
            ->orderBy('sort_order')
            ->get()
            ->map(function (MarketplaceCategory $category) use ($rankedKioskProducts) {
                $items = $this->itemsFor($category, $rankedKioskProducts);

                return [
                    'category' => $category,
                    'items' => $items->take($category->display_count),
                    'has_more' => $items->count() > $category->display_count,
                ];
            })
            ->all();
    }

    // the full, paginated view behind a row's own "Browse more"
    public function categoryPage(MarketplaceCategory $category, ?FarmerProfile $farmer, int $page): array
    {
        $items = $this->itemsFor($category, $this->kioskSearch->rankProducts($farmer));
        $perPage = $category->display_count;
        $total = $items->count();

        return [
            'items' => $items->forPage($page, $perPage)->values(),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    /** @return Collection<int, array> */
    public function search(string $query, ?FarmerProfile $farmer): Collection
    {
        $needle = mb_strtolower(trim($query));

        $kioskItems = $this->kioskSearch->rankProducts($farmer)
            ->filter(fn(array $row) => str_contains(mb_strtolower((string) $row['product_name']), $needle))
            ->map(fn(array $row) => $this->presentKioskProduct($row));

        $listingItems = ProduceListing::active()
            ->with('farmUnitStock.farmUnit.farmType', 'images')
            ->get()
            ->filter(fn(ProduceListing $listing) => str_contains(
                mb_strtolower((string) $listing->farmUnitStock?->farmUnit?->farmType?->name),
                $needle,
            ))
            ->map(fn(ProduceListing $listing) => $this->presentProduceListing($listing));

        return $kioskItems->concat($listingItems)->sortByDesc('score')->values();
    }

    private function itemsFor(MarketplaceCategory $category, Collection $rankedKioskProducts): Collection
    {
        $kioskProductIds = $category->kioskProducts()->pluck('kiosk_products.id');
        $listingIds = $category->produceListings()->pluck('produce_listings.id');

        $kioskItems = $rankedKioskProducts
            ->filter(fn(array $row) => $kioskProductIds->contains($row['kiosk_product_id']))
            ->map(fn(array $row) => $this->presentKioskProduct($row));

        $listingItems = ProduceListing::active()
            ->whereIn('id', $listingIds)
            ->with('farmUnitStock.farmUnit.farmType', 'images', 'sales')
            ->get()
            ->map(fn(ProduceListing $listing) => $this->presentProduceListing($listing));

        return $kioskItems->concat($listingItems)->sortByDesc('score')->values();
    }

    private function presentKioskProduct(array $row): array
    {
        return [
            'kind' => 'kiosk_product',
            'kiosk_product_id' => $row['kiosk_product_id'],
            'name' => $row['product_name'],
            'price_minor' => $row['price_minor'],
            'unit' => $row['unit'],
            'photo_url' => $row['image_url'],
            'link' => "/my-marketplace/kiosks/{$row['kiosk_uuid']}",
            'seller_label' => $row['kiosk_name'],
            'distance_label' => $row['distance_label'],
            'score' => $row['score'],
        ];
    }

    private function presentProduceListing(ProduceListing $listing): array
    {
        // no rating system exists for produce listings (Step 8 audit) - the only real
        // signal available is how many of its own sales actually settled, so that
        // stands in for salesCount; rating stays null, exactly what
        // blendedScore()'s own "gracefully handling zero ratings" path is for
        $salesCount = $listing->relationLoaded('sales')
            ? $listing->sales->filter(fn($sale) => $sale->isFullyConfirmed())->count()
            : $listing->sales()->get()->filter(fn($sale) => $sale->isFullyConfirmed())->count();

        $score = $this->kioskSearch->blendedScore(proximityScore: 0.5, ratingAverage: null, salesCount: $salesCount);

        $image = $listing->images->first();

        return [
            'kind' => 'produce_listing',
            'uuid' => $listing->uuid,
            'name' => $listing->farmUnitStock?->farmUnit?->farmType?->name,
            'price_minor' => null,
            'unit' => $listing->farmUnitStock?->unit_of_measure,
            'photo_url' => $image instanceof ProduceListingImage ? Storage::disk('public')->url($image->path) : null,
            'link' => "/produce-listings/{$listing->uuid}",
            'seller_label' => null,
            'distance_label' => null,
            'score' => $score,
        ];
    }
}
