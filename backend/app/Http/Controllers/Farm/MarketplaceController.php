<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Services\KioskSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function __construct(private readonly KioskSearchService $search) {}

    // the old standalone browse destination - Market Center replaced it (Step 8),
    // so old links/bookmarks land somewhere real instead of 404ing
    public function index(): RedirectResponse
    {
        return redirect()->route('market-center.index');
    }

    // a single kiosk's own storefront - only its products, still ranked/labelled through
    // the same KioskSearchService rather than a second, parallel query
    public function show(Request $request, Kiosk $kiosk): Response
    {
        $farmer = $this->resolveFarmerOrNull($request);

        $ranked = $this->search->rank($farmer);
        $row = $ranked->firstWhere('uuid', $kiosk->uuid);

        abort_if($row === null, 404);

        return Inertia::render('MyMarketplace/Kiosk', [
            'kiosk' => Arr::except($row, ['score', 'products']),
            'products' => $row['products'],
            'farmUnits' => $farmer?->farmUnits()->orderBy('name')->get(['id', 'name']) ?? collect(),
        ]);
    }

    // no longer aborts when absent - a buyer with a real permission grant but no
    // FarmerProfile (a supplier) still gets to browse, just without farm-type
    // suggestions or an order form; actually placing an order still goes through
    // OrderController, which keeps its own hard FarmerProfile requirement
    private function resolveFarmerOrNull(Request $request): ?FarmerProfile
    {
        return FarmerProfile::query()->where('user_id', $request->user()->id)->first();
    }
}
