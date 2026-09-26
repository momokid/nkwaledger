<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ProduceListing;
use App\Services\ContactRequestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

// the buyer-facing side of a produce listing - open to any authenticated account,
// never gated by the produce-listings permission that farmer/agent posting uses
class ProduceListingController extends Controller
{
    public function __construct(private readonly ContactRequestService $contactRequests) {}

    public function index(Request $request): Response
    {
        $listings = ProduceListing::active()
            ->with('farmUnitStock.farmUnit.farmType')
            ->latest()
            ->paginate(24)
            ->through(fn(ProduceListing $listing) => $this->summarize($listing));

        return Inertia::render('ProduceListings/Index', [
            'listings' => $listings,
        ]);
    }

    public function show(ProduceListing $listing): Response
    {
        abort_unless($listing->isActive(), 404);

        return Inertia::render('ProduceListings/Show', [
            'listing' => $this->summarize($listing->load('farmUnitStock.farmUnit.farmType')),
        ]);
    }

    public function interest(Request $request, ProduceListing $listing): RedirectResponse
    {
        abort_unless($listing->isActive(), 404);

        $data = $request->validate(['message' => ['nullable', 'string', 'max:500']]);

        $this->contactRequests->requestFor($listing, $request->user(), $data['message'] ?? null);

        return back()->with('success', 'Your interest was sent.');
    }

    private function summarize(ProduceListing $listing): array
    {
        return [
            'uuid' => $listing->uuid,
            'product_name' => $listing->farmUnitStock?->farmUnit?->farmType?->name,
            'quantity_remaining' => (float) $listing->quantity_remaining,
            'unit_of_measure' => $listing->farmUnitStock?->unit_of_measure,
            'photo_url' => $listing->photo !== null ? Storage::disk('public')->url($listing->photo) : null,
            'expires_at' => $listing->expires_at?->toDateString(),
        ];
    }
}
