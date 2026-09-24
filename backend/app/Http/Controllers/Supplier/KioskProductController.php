<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Http\Requests\Supplier\StoreKioskProductRequest;
use App\Http\Requests\Supplier\UpdateKioskProductPriceRequest;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\KioskProductImage;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Services\CatalogProductResolver;
use App\Support\PhotoUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class KioskProductController extends Controller
{
    public function __construct(private readonly CatalogProductResolver $resolver) {}

    public function index(Request $request, Kiosk $kiosk): Response
    {
        $this->guardOwnership($request, $kiosk);

        return Inertia::render('Supplier/KioskProducts/Index', [
            'kiosk' => ['uuid' => $kiosk->uuid, 'name' => $kiosk->name],
            'products' => $kiosk->kioskProducts()
                ->with(['catalogProduct.category', 'catalogProduct.unit', 'images'])
                ->orderByDesc('id')
                ->get()
                ->map(fn(KioskProduct $product) => $this->present($product)),
            'categories' => ProductCategory::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'requires_expiry_date']),
            'units' => ProductUnit::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreKioskProductRequest $request, Kiosk $kiosk): RedirectResponse
    {
        $this->guardOwnership($request, $kiosk);

        $data = $request->validated();
        $supplierUser = $request->user();

        $resolved = $this->resolver->resolve([
            'raw_code' => $data['raw_code'] ?? null,
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?? null,
            'unit_id' => $data['unit_id'] ?? null,
            'pack_quantity' => $data['pack_quantity'] ?? null,
        ], $supplierUser);

        $catalogProduct = $resolved->catalogProduct;

        if ($catalogProduct->category?->requires_expiry_date && empty($data['expiry_date'])) {
            throw ValidationException::withMessages([
                'expiry_date' => 'This category requires an expiry date.',
            ]);
        }

        $kioskProduct = DB::transaction(function () use ($kiosk, $catalogProduct, $data, $resolved, $supplierUser, $request) {
            $product = KioskProduct::create([
                'kiosk_id' => $kiosk->id,
                'catalog_product_id' => $catalogProduct->id,
                'price' => $data['price'],
                'expiry_date' => $data['expiry_date'] ?? $resolved->expiry?->toDateString(),
                'batch_number' => $resolved->batch,
            ]);

            $product->recordInitialPrice($supplierUser);

            foreach ($request->file('images', []) as $image) {
                $path = PhotoUpload::store($image, 'kiosk-products');
                $product->images()->create(['path' => $path]);
            }

            return $product;
        });

        return back()->with('success', "{$catalogProduct->name} added to {$kiosk->name}.");
    }

    public function updatePrice(UpdateKioskProductPriceRequest $request, Kiosk $kiosk, KioskProduct $kioskProduct): RedirectResponse
    {
        $this->guardOwnership($request, $kiosk);
        $this->guardBelongsToKiosk($kiosk, $kioskProduct);

        $kioskProduct->changePrice($request->validated()['price'], $request->user());

        return back()->with('success', 'Price updated.');
    }

    public function confirmPrice(Request $request, Kiosk $kiosk, KioskProduct $kioskProduct): RedirectResponse
    {
        $this->guardOwnership($request, $kiosk);
        $this->guardBelongsToKiosk($kiosk, $kioskProduct);

        $kioskProduct->confirmPriceUnchanged($request->user());

        return back()->with('success', 'Price confirmed unchanged.');
    }

    public function updateStock(Request $request, Kiosk $kiosk, KioskProduct $kioskProduct): RedirectResponse
    {
        $this->guardOwnership($request, $kiosk);
        $this->guardBelongsToKiosk($kiosk, $kioskProduct);

        $kioskProduct->update(['in_stock' => (bool) $request->boolean('in_stock')]);

        return back()->with('success', $kioskProduct->in_stock ? 'Marked in stock.' : 'Marked out of stock.');
    }

    public function destroyImage(Request $request, Kiosk $kiosk, KioskProduct $kioskProduct, KioskProductImage $image): RedirectResponse
    {
        $this->guardOwnership($request, $kiosk);
        $this->guardBelongsToKiosk($kiosk, $kioskProduct);

        if ($image->kiosk_product_id !== $kioskProduct->id) {
            throw new NotFoundHttpException();
        }

        Storage::disk('public')->delete($image->path);
        $image->delete();

        return back()->with('success', 'Image removed.');
    }

    private function guardOwnership(Request $request, Kiosk $kiosk): void
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null || $kiosk->supplier_id !== $supplier->id) {
            throw new AccessDeniedHttpException('This kiosk does not belong to you.');
        }
    }

    private function guardBelongsToKiosk(Kiosk $kiosk, KioskProduct $kioskProduct): void
    {
        if ($kioskProduct->kiosk_id !== $kiosk->id) {
            throw new NotFoundHttpException();
        }
    }

    private function present(KioskProduct $product): array
    {
        return [
            'uuid' => $product->uuid,
            'name' => $product->catalogProduct->name,
            'barcode' => $product->catalogProduct->barcode,
            'category' => $product->catalogProduct->category?->name,
            'unit' => $product->catalogProduct->unit?->name,
            'price' => $product->price,
            'in_stock' => $product->in_stock,
            'expiry_date' => $product->expiry_date?->toDateString(),
            'is_expired' => $product->isExpired(),
            'price_confirmed_at' => $product->price_confirmed_at,
            'images' => $product->images->map(fn(KioskProductImage $image) => [
                'id' => $image->id,
                'url' => Storage::disk('public')->url($image->path),
            ]),
        ];
    }
}
