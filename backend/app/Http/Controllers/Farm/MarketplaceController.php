<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\ProductCategory;
use App\Services\KioskSearchService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceController extends Controller
{
    public function __construct(private readonly KioskSearchService $search) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $categoryId = $request->query('category') !== null ? (int) $request->query('category') : null;

        $ranked = $this->search->rank($farmer, $categoryId);

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 15;

        // paged in php, since the ranking is a blended score computed here, not something
        // the database can order by directly
        $kiosks = (new LengthAwarePaginator(
            $ranked->forPage($page, $perPage)->values()->map(fn(array $row) => Arr::except($row, 'score')),
            $ranked->count(),
            $perPage,
            $page,
            ['path' => $request->url()],
        ))->appends($request->except('page'));

        return Inertia::render('MyMarketplace/Index', [
            'kiosks' => $kiosks,
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'filters' => $request->only(['category']),
            'farmUnits' => $farmer->farmUnits()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    private function resolveFarmer(Request $request): FarmerProfile
    {
        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }
}
