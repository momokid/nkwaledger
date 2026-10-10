<?php

namespace App\Http\Controllers;

use App\Models\FarmerProfile;
use App\Models\MarketplaceCategory;
use App\Services\MarketCenterService;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// the single unified buyer-browsing homepage every role reaches Market Center
// through - replaces /my-marketplace and /produce-listings as separate destinations
class MarketCenterController extends Controller
{
    public function __construct(
        private readonly MarketCenterService $marketCenter,
        private readonly SettingsService $settings,
    ) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmerOrNull($request);
        $query = trim((string) $request->query('q', ''));

        if ($query !== '') {
            return Inertia::render('MarketCenter/Index', [
                'announcement' => $this->announcement(),
                'query' => $query,
                'searchResults' => $this->marketCenter->search($query, $farmer)
                    ->map(fn(array $item) => $this->present($item))
                    ->values(),
                'rows' => null,
            ]);
        }

        $rows = collect($this->marketCenter->homepage($farmer))->map(fn(array $row) => [
            'category' => [
                'slug' => $row['category']->slug,
                'name' => $row['category']->name,
            ],
            'items' => $row['items']->map(fn(array $item) => $this->present($item))->values(),
            'has_more' => $row['has_more'],
        ]);

        return Inertia::render('MarketCenter/Index', [
            'announcement' => $this->announcement(),
            'query' => null,
            'searchResults' => null,
            'rows' => $rows,
        ]);
    }

    // one category's own full, paginated view behind "Browse more"
    public function category(Request $request, MarketplaceCategory $marketplaceCategory): Response
    {
        abort_unless($marketplaceCategory->is_active, 404);

        $farmer = $this->resolveFarmerOrNull($request);
        $page = max(1, (int) $request->query('page', 1));

        $paged = $this->marketCenter->categoryPage($marketplaceCategory, $farmer, $page);

        return Inertia::render('MarketCenter/Category', [
            'category' => ['slug' => $marketplaceCategory->slug, 'name' => $marketplaceCategory->name],
            'items' => collect($paged['items'])->map(fn(array $item) => $this->present($item))->values(),
            'total' => $paged['total'],
            'perPage' => $paged['per_page'],
            'currentPage' => $paged['current_page'],
            'lastPage' => $paged['last_page'],
        ]);
    }

    private function present(array $item): array
    {
        return [
            'kind' => $item['kind'],
            'id' => $item['kind'] === 'kiosk_product' ? $item['kiosk_product_id'] : $item['uuid'],
            'name' => $item['name'],
            'price_minor' => $item['price_minor'],
            'unit' => $item['unit'],
            'photo_url' => $item['photo_url'],
            'link' => $item['link'],
            'seller_label' => $item['seller_label'],
            'distance_label' => $item['distance_label'],
        ];
    }

    private function announcement(): ?array
    {
        $message = trim((string) $this->settings->get('marketplace.announcement_message'));

        if ($message === '') {
            return null;
        }

        return [
            'title' => $this->settings->get('marketplace.announcement_title'),
            'message' => $message,
            'link' => $this->settings->get('marketplace.announcement_link'),
        ];
    }

    private function resolveFarmerOrNull(Request $request): ?FarmerProfile
    {
        return FarmerProfile::query()->where('user_id', $request->user()->id)->first();
    }
}
