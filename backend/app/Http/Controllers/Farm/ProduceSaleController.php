<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\ProduceSale;
use App\Services\ProduceSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProduceSaleController extends Controller
{
    public function __construct(private readonly ProduceSaleService $sales) {}

    // the farmer's tap - accepting the buyer's offer as it stands
    public function confirm(Request $request, ProduceSale $sale): RedirectResponse
    {
        $user = $request->user();

        abort_if($sale->farmerProfile?->user_id !== $user->id && ! $user->hasRole('admin'), 403);

        $this->sales->confirm($sale, $user);

        return back()->with('success', 'Sale confirmed.');
    }
}
