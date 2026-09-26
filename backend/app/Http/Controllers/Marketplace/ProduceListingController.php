<?php

namespace App\Http\Controllers\Marketplace;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use App\Models\ProduceListing;
use App\Services\ContactRequestService;
use App\Support\Phone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

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

        $data = $request->validate([
            'sender_phone' => ['required', 'string', function ($attribute, $value, $fail) {
                if (Phone::normalise($value) === null) {
                    $fail('That doesn\'t look like a Ghanaian mobile number. Try it the way you\'d dial it, like 0244445566.');
                }
            }],
            'message' => ['nullable', 'string', 'max:500'],
        ]);

        $this->contactRequests->requestFor(
            $listing,
            $request->user(),
            Phone::normalise($data['sender_phone']),
            $data['message'] ?? null,
        );

        return back()->with('success', 'Your interest was sent.');
    }

    // the recipient's own reply - the only action that ever reveals either side's
    // number, and only to this request's two parties
    public function reply(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        abort_if($contactRequest->recipient_user_id !== $request->user()->id, 403);

        $data = $request->validate(['reply_message' => ['required', 'string', 'max:500']]);

        try {
            $this->contactRequests->reply($contactRequest, $request->user(), $data['reply_message']);
        } catch (InvalidArgumentException $failure) {
            return back()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Reply sent.');
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
