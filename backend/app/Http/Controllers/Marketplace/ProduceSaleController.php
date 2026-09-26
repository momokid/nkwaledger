<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use App\Services\ProduceSaleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ProduceSaleController extends Controller
{
    public function __construct(private readonly ProduceSaleService $sales) {}

    public function store(Request $request, ProduceListing $listing): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', 'in:bank,cod'],
            'amount' => ['required', 'numeric', 'gt:0'],
        ]);

        try {
            $this->sales->submit(
                $listing,
                $request->user(),
                (string) $data['quantity'],
                $data['payment_method'],
                (string) $data['amount'],
            );
        } catch (InvalidArgumentException $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Your offer was sent to the farmer.');
    }

    // the buyer's tap - confirming they received the produce
    public function receive(Request $request, ProduceSale $sale): RedirectResponse
    {
        abort_if($sale->buyer_user_id !== $request->user()->id, 403);

        $this->sales->receive($sale, $request->user());

        return back()->with('success', 'Thanks for confirming.');
    }
}
