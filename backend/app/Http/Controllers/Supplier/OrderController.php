<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): Response
    {
        $supplier = $request->user()->supplier;

        $orders = $supplier
            ? Order::query()
                ->whereHas('kiosk', fn($query) => $query->where('supplier_id', $supplier->id))
                ->with(['kiosk:id,name', 'farmerProfile.user:id,surname,first_name', 'items.kioskProduct.catalogProduct:id,name'])
                ->orderByDesc('requested_at')
                ->get()
                ->map(fn(Order $order) => [
                    'uuid' => $order->uuid,
                    'order_number' => $order->order_number,
                    'kiosk_name' => $order->kiosk?->name,
                    'farmer_name' => trim("{$order->farmerProfile?->user?->surname} {$order->farmerProfile?->user?->first_name}"),
                    'status' => $order->status->value,
                    // the supplier's own "needs my confirm" state is this exact timestamp,
                    // not the friendly status label - a farmer tapping Received first
                    // still shows status "received" even though the supplier hasn't acted
                    'confirmed_at' => $order->confirmed_at,
                    'payment_method' => $order->payment_method->value,
                    'amount_minor' => $order->amount_minor,
                    'requested_at' => $order->requested_at,
                    'items' => $order->items->map(fn($item) => [
                        'product' => $item->kioskProduct?->catalogProduct?->name,
                        'quantity' => $item->quantity,
                    ]),
                ])
            : collect();

        return Inertia::render('Supplier/Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null || $order->kiosk?->supplier_id !== $supplier->id) {
            abort(403);
        }

        $this->orders->confirm($order, $request->user());

        return back()->with('success', "Order {$order->order_number} confirmed.");
    }
}
