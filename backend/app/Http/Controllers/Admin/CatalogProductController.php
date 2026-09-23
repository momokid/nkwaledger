<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BarcodeType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\MergeCatalogProductRequest;
use App\Http\Requests\Admin\StoreCatalogProductRequest;
use App\Models\CatalogProduct;
use App\Models\KioskProduct;
use App\Models\KioskProductImage;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Services\AccessControlService;
use App\Services\CatalogProductMergeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CatalogProductController extends Controller
{
    public function __construct(
        private readonly CatalogProductMergeService $merger,
        private readonly AccessControlService $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Marketplace/CatalogProducts/Index', [
            'products' => CatalogProduct::query()
                ->whereNull('merged_into_id')
                ->with(['category', 'unit'])
                ->withCount('kioskProducts')
                ->orderByDesc('id')
                ->paginate(15)
                ->through(fn(CatalogProduct $product) => [
                    'uuid' => $product->uuid,
                    'barcode' => $product->barcode,
                    'name' => $product->name,
                    'category' => $product->category?->name,
                    'unit' => $product->unit?->name,
                    'seeded' => $product->seeded,
                    'kiosks_count' => $product->kiosk_products_count,
                ]),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'units' => ProductUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'permissions' => [
                'create' => $this->access->can($user, 'marketplace-catalog.create'),
                'merge' => $this->access->can($user, 'marketplace-catalog.merge'),
            ],
        ]);
    }

    public function store(StoreCatalogProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        CatalogProduct::create([
            'barcode' => $data['barcode'] ?? null,
            'barcode_type' => isset($data['barcode']) ? BarcodeType::Barcode : null,
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'pack_quantity' => $data['pack_quantity'] ?? null,
            'created_by' => $request->user()->id,
            'seeded' => true,
        ]);

        return back()->with('success', "{$data['name']} added to the catalog.");
    }

    public function merge(MergeCatalogProductRequest $request, CatalogProduct $catalogProduct): RedirectResponse
    {
        $keeper = CatalogProduct::where('uuid', $request->validated()['into'])->firstOrFail();

        $this->merger->merge($catalogProduct, $keeper);

        return back()->with('success', "{$catalogProduct->name} merged into {$keeper->name}.");
    }

    // a photo of a code is not proof the supplier stocks the product; admin can
    // pull an image down without waiting on the supplier to do it themselves
    public function destroyImage(KioskProduct $kioskProduct, KioskProductImage $image): RedirectResponse
    {
        if ($image->kiosk_product_id !== $kioskProduct->id) {
            throw new NotFoundHttpException();
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }
}
