<?php

namespace App\Services;

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\ProductCategory;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

// ranks kiosks for a farmer's marketplace search: one blended score per kiosk, never
// distance-first-with-tiebreakers, so a well-rated kiosk slightly farther away can
// genuinely outrank a mediocre closer one
class KioskSearchService
{
    // distance is the strongest signal, since the farmer still has to travel there in
    // person; rating reflects real experience; sales count is the weakest signal, since
    // it is easily skewed by how long a kiosk has existed rather than how good it is
    private const WEIGHT_PROXIMITY = 0.5;
    private const WEIGHT_RATING = 0.3;
    private const WEIGHT_SALES = 0.2;

    // a kiosk whose products fit the farmer's own farm type gets a flat bump on top of
    // the blended score - large enough to flip a close call, never enough on its own to
    // beat a kiosk that is genuinely much better matched on distance/rating/sales
    private const FARM_TYPE_BOOST = 0.15;

    // the sales component saturates rather than growing forever, so one long-running
    // kiosk cannot dominate that component indefinitely once Step 5 exists
    private const SALES_SATURATION = 50.0;

    // real haversine distance decays smoothly against this many km once both ends have
    // coordinates; a discrete same-district/region/elsewhere tier is used otherwise,
    // since coordinates are the exception, not the rule, on this schema today (see the
    // Step 4 audit: kiosks.latitude/longitude and communities.latitude/longitude are
    // both nullable, and neither is populated by the ordinary kiosk-registration flow)
    private const PROXIMITY_DECAY_KM = 20.0;
    private const TIER_SAME_DISTRICT = 0.8;
    private const TIER_SAME_REGION = 0.5;
    private const TIER_ELSEWHERE = 0.2;

    // the starting list this feature's own spec calls out (crop farmers see fertilizer/
    // seed/chemical first, animal farmers see feed/vet first); no ProductCategory<->
    // FarmTypeCategory mapping table exists yet, and six fixed category names never
    // needing an admin-editable UI does not justify adding one - see the Step 4 report
    private const FARM_TYPE_CATEGORY_SUGGESTIONS = [
        'Crop' => ['Fertilizer', 'Seed', 'Chemical'],
        'Livestock' => ['Feed', 'Veterinary Drugs'],
        'Aquatic' => ['Feed', 'Veterinary Drugs'],
    ];

    // every kiosk with at least one available (in-stock, active, unexpired) product,
    // optionally narrowed to one category, ranked highest score first. $farmer is
    // nullable so a non-farmer buyer (a supplier browsing as a buyer, say) can still
    // browse - they just get no community-based proximity and no farm-type boost,
    // never a crash from a relation call on a farmer that does not exist
    public function rank(?FarmerProfile $farmer, ?int $categoryId = null): Collection
    {
        $farmer?->loadMissing('community.district.region');
        $community = $farmer?->community;

        $suggestedCategoryIds = $this->suggestedCategoryIdsFor($farmer);

        $kiosks = Kiosk::query()
            ->visible()
            ->whereHas('kioskProducts', function ($query) use ($categoryId) {
                $query->available();

                if ($categoryId !== null) {
                    $query->whereHas('catalogProduct', fn($q) => $q->where('category_id', $categoryId));
                }
            })
            ->with([
                'supplier:id,business_name',
                'district:id,name,region_id',
                'district.region:id,name',
                'kioskProducts' => fn($query) => $query->available()->with(['catalogProduct.category', 'catalogProduct.unit', 'images']),
            ])
            ->withAvg('reviews', 'rating')
            ->withCount('reviews')
            ->withCount(['orders as completed_sales_count' => function ($query) {
                $query->whereNotNull('confirmed_at')->whereNotNull('received_at')->whereNull('closed_at');
            }])
            ->get();

        return $kiosks
            ->map(fn(Kiosk $kiosk) => $this->present($kiosk, $community, $suggestedCategoryIds))
            ->sortByDesc('score')
            ->values();
    }

    // the product grid flattens rank()'s kiosk-level rows into one row per product - a
    // kiosk with 5 products contributes 5 rows, each still carrying that kiosk's own
    // blended score, so the grid's order is never rebuilt, only unpacked
    public function rankProducts(?FarmerProfile $farmer, ?int $categoryId = null): Collection
    {
        return $this->rank($farmer, $categoryId)
            ->flatMap(fn(array $kiosk) => collect($kiosk['products'])->map(fn(array $product) => [
                'kiosk_uuid' => $kiosk['uuid'],
                'kiosk_name' => $kiosk['name'],
                'distance_label' => $kiosk['distance_label'],
                'contact_phone' => $kiosk['contact_phone'],
                'kiosk_product_id' => $product['kiosk_product_id'],
                'product_name' => $product['name'],
                'price_minor' => $product['price_minor'],
                'unit' => $product['unit'],
                'image_url' => $product['image_url'],
                'score' => $kiosk['score'],
            ]))
            ->values();
    }

    // exposed on its own so the weighting itself is directly testable with constructed
    // inputs, independent of the query/eager-loading above
    public function blendedScore(float $proximityScore, ?float $ratingAverage, int $salesCount): float
    {
        $ratingScore = $ratingAverage === null ? 0.0 : min(max($ratingAverage / 5, 0.0), 1.0);
        $salesScore = min($salesCount / self::SALES_SATURATION, 1.0);

        return self::WEIGHT_PROXIMITY * $proximityScore
            + self::WEIGHT_RATING * $ratingScore
            + self::WEIGHT_SALES * $salesScore;
    }

    private function present(Kiosk $kiosk, ?Community $community, array $suggestedCategoryIds): array
    {
        $proximity = $this->proximityFor($kiosk, $community);
        $rating = $this->ratingSummaryFor($kiosk);
        $salesCount = $this->completedSalesCountFor($kiosk);
        $matchesFarmType = $this->matchesFarmType($kiosk, $suggestedCategoryIds);

        $score = $this->blendedScore($proximity['score'], $rating['average'], $salesCount)
            + ($matchesFarmType ? self::FARM_TYPE_BOOST : 0.0);

        return [
            'uuid' => $kiosk->uuid,
            'name' => $kiosk->name,
            'supplier' => $kiosk->supplier?->business_name,
            'contact_phone' => $kiosk->contact_phone,
            'distance_label' => $proximity['label'],
            'categories' => $kiosk->kioskProducts
                ->pluck('catalogProduct.category.name')
                ->filter()
                ->unique()
                ->values()
                ->all(),
            // what the farmer can actually order right now - only ever the available
            // (in-stock, active, unexpired) products already loaded above
            'products' => $kiosk->kioskProducts
                ->map(fn(KioskProduct $product) => [
                    'kiosk_product_id' => $product->id,
                    'name' => $product->catalogProduct?->name,
                    'price_minor' => $product->price,
                    'unit' => $product->catalogProduct?->unit?->name,
                    'image_url' => $this->imageUrlFor($product),
                ])
                ->values()
                ->all(),
            'rating_average' => $rating['average'],
            'review_count' => $rating['count'],
            'sales_count' => $salesCount,
            'matches_farm_type' => $matchesFarmType,
            'score' => $score,
        ];
    }

    /** @return array{score: float, label: string} */
    private function proximityFor(Kiosk $kiosk, ?Community $community): array
    {
        if ($community === null) {
            return ['score' => self::TIER_ELSEWHERE, 'label' => 'Location unknown'];
        }

        if ($community->latitude !== null && $community->longitude !== null
            && $kiosk->latitude !== null && $kiosk->longitude !== null) {
            $km = $this->haversineKm(
                (float) $community->latitude,
                (float) $community->longitude,
                (float) $kiosk->latitude,
                (float) $kiosk->longitude,
            );

            return [
                'score' => 1 / (1 + $km / self::PROXIMITY_DECAY_KM),
                'label' => $km < 1 ? 'Less than 1 km away' : round($km) . ' km away',
            ];
        }

        if ($community->district_id === $kiosk->district_id) {
            return ['score' => self::TIER_SAME_DISTRICT, 'label' => 'Your district'];
        }

        if ($community->district?->region_id === $kiosk->region_id) {
            return ['score' => self::TIER_SAME_REGION, 'label' => 'Your region'];
        }

        return ['score' => self::TIER_ELSEWHERE, 'label' => $kiosk->district?->name ?? 'Elsewhere'];
    }

    // the first photo a supplier attached, if any - never fabricated, a product with none
    // simply has no image_url and the frontend shows its own placeholder
    private function imageUrlFor(KioskProduct $product): ?string
    {
        $image = $product->images->first();

        return $image === null ? null : Storage::disk('public')->url($image->path);
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return $earthRadiusKm * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    // Step 5 (orders/reviews) has landed: reviews_avg_rating/reviews_count come from the
    // ->withAvg()/->withCount() pair added to the query in rank() - null/0 exactly as
    // before for a kiosk nobody has reviewed yet, never fabricated
    private function ratingSummaryFor(Kiosk $kiosk): array
    {
        $average = $kiosk->reviews_avg_rating;

        return [
            'average' => $average === null ? null : (float) $average,
            'count' => (int) ($kiosk->reviews_count ?? 0),
        ];
    }

    // completed_sales_count comes from the same query - an order counts only once both
    // taps have landed and it never closed, exactly Order::isFullyConfirmed()'s own rule
    private function completedSalesCountFor(Kiosk $kiosk): int
    {
        return (int) ($kiosk->completed_sales_count ?? 0);
    }

    /** @return int[] */
    private function suggestedCategoryIdsFor(?FarmerProfile $farmer): array
    {
        if ($farmer === null) {
            return [];
        }

        $categoryNames = $farmer->farmTypes()
            ->with('category')
            ->get()
            ->pluck('category.name')
            ->filter()
            ->unique()
            ->flatMap(fn(string $name) => self::FARM_TYPE_CATEGORY_SUGGESTIONS[$name] ?? [])
            ->unique();

        if ($categoryNames->isEmpty()) {
            return [];
        }

        return ProductCategory::query()->whereIn('name', $categoryNames)->pluck('id')->all();
    }

    private function matchesFarmType(Kiosk $kiosk, array $suggestedCategoryIds): bool
    {
        if ($suggestedCategoryIds === []) {
            return false;
        }

        return $kiosk->kioskProducts->contains(
            fn(KioskProduct $product) => in_array($product->catalogProduct?->category_id, $suggestedCategoryIds, true),
        );
    }
}
