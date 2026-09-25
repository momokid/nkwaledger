<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KioskStatus;
use App\Enums\SupplierAccountStatus;
use App\Http\Controllers\Controller;
use App\Models\KioskProduct;
use App\Models\Supplier;
use App\Models\KioskReport;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\KioskReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AccessControlService $access,
        private readonly KioskReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Marketplace/Suppliers/Index', [
            'suppliers' => Supplier::query()
                ->with('user:id,surname,first_name,phone,phone_verified_at')
                ->withCount('kiosks')
                ->orderByDesc('id')
                ->paginate(15)
                ->through(fn(Supplier $supplier) => [
                    'uuid' => $supplier->uuid,
                    'business_name' => $supplier->business_name,
                    'email' => $supplier->email,
                    'phone' => $supplier->user?->phone,
                    'verification_status' => $supplier->verificationStatus(),
                    'account_status' => $supplier->account_status->value,
                    'kiosks_count' => $supplier->kiosks_count,
                ]),
            'permissions' => [
                'suspend' => $this->access->can($user, 'marketplace-suppliers.suspend'),
            ],
        ]);
    }

    // suspending a supplier suspends every one of their kiosks in one action; restoring is separate
    public function suspend(Request $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update([
            'account_status' => SupplierAccountStatus::Suspended,
            'suspended_at' => now(),
            'suspended_by' => $request->user()->id,
            'suspension_reason' => $request->input('reason'),
        ]);

        $supplier->kiosks()->update(['status' => KioskStatus::Suspended]);

        KioskReport::query()
            ->whereIn('kiosk_id', $supplier->kiosks()->pluck('id'))
            ->whereIn('status', ['open', 'supplier_answered', 'with_admin'])
            ->get()
            ->each(fn(KioskReport $report) => $this->reports->markSuspended($report));

        $this->audit->recordOn('marketplace_supplier.suspended', $supplier);

        return back()->with('success', "{$supplier->business_name} and all of its kiosks are suspended.");
    }

    // admin can inspect what a supplier has listed, across every one of their kiosks
    public function stock(Supplier $supplier): Response
    {
        return Inertia::render('Admin/Marketplace/Suppliers/Stock', [
            'supplier' => ['uuid' => $supplier->uuid, 'business_name' => $supplier->business_name],
            'products' => KioskProduct::query()
                ->whereHas('kiosk', fn($query) => $query->where('supplier_id', $supplier->id))
                ->with(['catalogProduct', 'kiosk', 'images'])
                ->orderByDesc('id')
                ->get()
                ->map(fn(KioskProduct $product) => [
                    'uuid' => $product->uuid,
                    'kiosk' => $product->kiosk->name,
                    'name' => $product->catalogProduct->name,
                    'barcode' => $product->catalogProduct->barcode,
                    'price' => $product->price,
                    'in_stock' => $product->in_stock,
                    'expiry_date' => $product->expiry_date?->toDateString(),
                    'status' => $product->status->value,
                    'images' => $product->images->map(fn($image) => [
                        'id' => $image->id,
                        'url' => Storage::disk('public')->url($image->path),
                    ]),
                ]),
        ]);
    }

    public function restore(Supplier $supplier): RedirectResponse
    {
        $supplier->update([
            'account_status' => SupplierAccountStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
        ]);

        $this->audit->recordOn('marketplace_supplier.restored', $supplier);

        return back()->with('success', "{$supplier->business_name} is restored. Its kiosks stay suspended until restored individually.");
    }
}
