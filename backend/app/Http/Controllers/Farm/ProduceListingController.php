<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnitStock;
use App\Models\LedgerAccount;
use App\Models\ProduceListing;
use App\Services\ProduceListingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

// reused for both a farmer's own "my-listings" and an agent posting/managing on a
// farmer's behalf, same dual-route convention RecordTransactionController already uses
class ProduceListingController extends Controller
{
    public function __construct(private readonly ProduceListingService $listings) {}

    public function index(Request $request, ?FarmerProfile $farmer = null): Response
    {
        $farmer = $this->resolveFarmer($request, $farmer);

        return Inertia::render('MyListings/Index', [
            'farmer' => ['id' => $farmer->uuid],
            'listings' => $farmer->produceListings()
                ->with('farmUnitStock.farmUnit.farmType')
                ->latest()
                ->get()
                ->map(fn(ProduceListing $listing) => $this->present($listing)),
            'stockBatches' => FarmUnitStock::query()
                ->whereHas('farmUnit', fn($query) => $query->where('farmer_profile_id', $farmer->id))
                ->confirmed()
                ->whereNull('ended_on')
                ->with('farmUnit.farmType')
                ->get()
                ->map(fn(FarmUnitStock $stock) => [
                    'id' => $stock->id,
                    'label' => $stock->farmUnit?->farmType?->name ?? $stock->farmUnit?->name,
                    'current_quantity' => (float) $stock->current_quantity,
                    'unit_of_measure' => $stock->unit_of_measure,
                ]),
            'settlementAccounts' => LedgerAccount::settlement()
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ?FarmerProfile $farmer = null): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request, $farmer);

        $data = $request->validate([
            'farm_unit_stock_id' => ['required', 'integer'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'crop_expiry_days' => ['nullable', 'integer', 'min:1'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $stock = FarmUnitStock::query()
            ->whereHas('farmUnit', fn($query) => $query->where('farmer_profile_id', $farmer->id))
            ->findOrFail($data['farm_unit_stock_id']);

        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store('produce-listings', 'public')
            : null;

        try {
            $this->listings->create(
                $stock,
                $farmer,
                $request->user(),
                (float) $data['quantity'],
                $data['crop_expiry_days'] ?? null,
                $photoPath,
            );
        } catch (\InvalidArgumentException $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Listing posted.');
    }

    public function agree(Request $request, ProduceListing $listing): RedirectResponse
    {
        $this->authorizeOwnListing($request, $listing);

        $this->listings->agree($listing);

        return back()->with('success', 'Listing is now live.');
    }

    public function withdraw(Request $request, ProduceListing $listing): RedirectResponse
    {
        $this->authorizeOwnListing($request, $listing);

        $this->listings->withdraw($listing);

        return back()->with('success', 'Listing withdrawn.');
    }

    public function markSold(Request $request, ProduceListing $listing): RedirectResponse
    {
        $this->authorizeOwnListing($request, $listing);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'settlement_account_id' => ['required', 'integer'],
        ]);

        try {
            $this->listings->markSold(
                $listing,
                (string) $data['amount'],
                (string) $data['quantity'],
                (int) $data['settlement_account_id'],
                $request->user()->id,
            );
        } catch (\Throwable $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Sale recorded. Your record is in your book.');
    }

    private function present(ProduceListing $listing): array
    {
        return [
            'uuid' => $listing->uuid,
            'status' => $listing->status->value,
            'product_name' => $listing->farmUnitStock?->farmUnit?->farmType?->name,
            'quantity_listed' => (float) $listing->quantity_listed,
            'quantity_remaining' => (float) $listing->quantity_remaining,
            'unit_of_measure' => $listing->farmUnitStock?->unit_of_measure,
            'photo_url' => $listing->photo !== null ? Storage::disk('public')->url($listing->photo) : null,
            'expires_at' => $listing->expires_at?->toDateString(),
            'farmer_agreed_at' => $listing->farmer_agreed_at?->toDateTimeString(),
            'is_agent_posted_draft' => $listing->isDraft(),
        ];
    }

    private function authorizeOwnListing(Request $request, ProduceListing $listing): void
    {
        $user = $request->user();

        $isOwnFarmer = $listing->farmerProfile?->user_id === $user->id;
        $isAssignedAgent = $listing->farmerProfile?->assigned_agent_id === $user->id;

        abort_if(! $isOwnFarmer && ! $isAssignedAgent && ! $user->hasRole('admin'), 403);
    }

    // the farmer's own page names nobody, the agent's page names the farmer -
    // identical to RecordTransactionController::resolveFarmer()
    private function resolveFarmer(Request $request, ?FarmerProfile $farmer): FarmerProfile
    {
        $user = $request->user();

        if ($farmer === null) {
            $own = FarmerProfile::query()->where('user_id', $user->id)->first();

            abort_if($own === null, 403);

            return $own;
        }

        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);

        return $farmer;
    }
}
