<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\ProduceSale;
use App\Services\ProduceSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProduceSaleController extends Controller
{
    public function __construct(private readonly ProduceSaleService $sales) {}

    // vouching for a sale so it counts toward the farmer's credit score - never an
    // approval admin needs to grant, see the Step 7 audit
    public function coConfirm(Request $request, ProduceSale $sale): RedirectResponse
    {
        $this->sales->coConfirm($sale, $request->user());

        return back()->with('success', 'Sale co-confirmed.');
    }
}
