<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProduceSale;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// visibility only - admin never approves a produce sale, only sees it; the credit
// co-confirm is the agent's own action, see Agent\ProduceSaleController
class ProduceSaleController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Marketplace/ProduceSales/Index', [
            'sales' => ProduceSale::query()
                ->with(['farmerProfile.user', 'buyer', 'agent', 'produceListing.farmUnitStock.farmUnit.farmType'])
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn(ProduceSale $sale) => [
                    'uuid' => $sale->uuid,
                    'farmer' => trim("{$sale->farmerProfile?->user?->surname} {$sale->farmerProfile?->user?->first_name}"),
                    'buyer' => trim("{$sale->buyer?->surname} {$sale->buyer?->first_name}"),
                    'product_name' => $sale->produceListing?->farmUnitStock?->farmUnit?->farmType?->name,
                    'quantity' => (float) $sale->quantity,
                    'amount_minor' => $sale->amount_minor,
                    'status' => $sale->status->value,
                    'agent' => $sale->agent !== null ? trim("{$sale->agent->surname} {$sale->agent->first_name}") : null,
                    'counts_toward_credit' => $sale->countsTowardCredit(),
                    'requested_at' => $sale->requested_at,
                ]),
        ]);
    }
}
