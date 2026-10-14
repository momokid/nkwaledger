<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMarketplaceCategoryRequest;
use App\Http\Requests\Admin\UpdateMarketplaceCategoryRequest;
use App\Models\MarketplaceCategory;
use App\Services\AccessControlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class MarketplaceCategoryController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Marketplace/Categories/Index', [
            'categories' => MarketplaceCategory::query()
                ->withCount(['kioskProducts', 'produceListings'])
                ->orderBy('sort_order')
                ->get(),
            'permissions' => [
                'create' => $this->access->can($user, 'marketplace-categories.create'),
                'update' => $this->access->can($user, 'marketplace-categories.update'),
                'delete' => $this->access->can($user, 'marketplace-categories.delete'),
            ],
        ]);
    }

    public function store(StoreMarketplaceCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        MarketplaceCategory::create([
            ...$data,
            'slug' => $this->uniqueSlug($data['name']),
            // new categories go to the end of the row order by default
            'sort_order' => (MarketplaceCategory::max('sort_order') ?? 0) + 1,
        ]);

        return back()->with('success', 'Category created.');
    }

    public function update(UpdateMarketplaceCategoryRequest $request, MarketplaceCategory $marketplaceCategory): RedirectResponse
    {
        $data = $request->validated();

        $marketplaceCategory->update([
            ...$data,
            'slug' => $data['name'] === $marketplaceCategory->name
                ? $marketplaceCategory->slug
                : $this->uniqueSlug($data['name'], $marketplaceCategory->id),
        ]);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(MarketplaceCategory $marketplaceCategory): RedirectResponse
    {
        $marketplaceCategory->delete();

        return back()->with('success', 'Category deleted.');
    }

    // the homepage's row order - one call, every category's new position at once,
    // rather than a drag-and-drop firing an update per row
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer', 'exists:marketplace_categories,id'],
        ]);

        foreach ($data['ids'] as $position => $id) {
            MarketplaceCategory::where('id', $id)->update(['sort_order' => $position]);
        }

        return back()->with('success', 'Order saved.');
    }

    private function uniqueSlug(string $name, ?int $excludingId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $suffix = 2;

        while (MarketplaceCategory::withTrashed()
            ->where('slug', $slug)
            ->when($excludingId !== null, fn($query) => $query->where('id', '!=', $excludingId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
