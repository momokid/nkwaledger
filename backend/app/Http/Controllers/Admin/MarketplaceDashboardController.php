<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use Inertia\Inertia;
use Inertia\Response;

// minimal monitoring, not the buyer-browsing homepage - admin reaches that
// separately at Market Center like every other role
class MarketplaceDashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Marketplace/Dashboard/Index', [
            'stats' => [
                'active_kiosk_products' => KioskProduct::query()->available()->count(),
                'active_produce_listings' => ProduceListing::query()->active()->count(),
                'orders_last_7_days' => Order::query()->where('requested_at', '>=', now()->subDays(7))->count(),
                'produce_sales_last_7_days' => ProduceSale::query()->where('requested_at', '>=', now()->subDays(7))->count(),
            ],
        ]);
    }
}
