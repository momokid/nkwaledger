<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\Order;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class OrderController extends Controller
{
    public function __construct(private readonly OrderService $orders) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $orders = $farmer->orders()
            ->with(['kiosk:id,name', 'items.kioskProduct.catalogProduct:id,name', 'review:id,order_id'])
            ->orderByDesc('requested_at')
            ->get()
            ->map(fn(Order $order) => [
                'uuid' => $order->uuid,
                'order_number' => $order->order_number,
                'kiosk_name' => $order->kiosk?->name,
                'status' => $order->status->value,
                'received_at' => $order->received_at,
                'is_fully_confirmed' => $order->isFullyConfirmed(),
                'has_review' => $order->review !== null,
                'amount_minor' => $order->amount_minor,
                'items' => $order->items->map(fn($item) => [
                    'product' => $item->kioskProduct?->catalogProduct?->name,
                    'quantity' => $item->quantity,
                ]),
            ]);

        return Inertia::render('MyMarketplace/Orders', [
            'orders' => $orders,
        ]);
    }

    public function store(Request $request, Kiosk $kiosk): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request);

        $validated = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.kiosk_product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'payment_method' => ['required', 'string', 'in:bank,cod'],
            // the farmer chooses which part of the farm this purchase is for right here
            // at checkout - the input_purchase ledger template requires one, and the
            // spec never says where to ask, so this is the simplest reasonable point
            'farm_unit_id' => ['required', 'integer', 'exists:farm_units,id'],
            'facilitating_agent_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $ownsFarmUnit = $farmer->farmUnits()->where('id', $validated['farm_unit_id'])->exists();

        if (! $ownsFarmUnit) {
            throw ValidationException::withMessages(['farm_unit_id' => 'Choose one of your own farm units.']);
        }

        // must genuinely hold the agent role - a commission (real money) rides on this
        if (isset($validated['facilitating_agent_id'])) {
            $isAgent = User::find($validated['facilitating_agent_id'])?->hasRole('agent') ?? false;

            if (! $isAgent) {
                throw ValidationException::withMessages(['facilitating_agent_id' => 'That user is not an agent.']);
            }
        }

        try {
            $order = $this->orders->submitCart(
                farmer: $farmer,
                kiosk: $kiosk,
                items: $validated['items'],
                paymentMethod: $validated['payment_method'],
                farmUnitId: $validated['farm_unit_id'],
                facilitatingAgentId: $validated['facilitating_agent_id'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['items' => $exception->getMessage()]);
        }

        return back()->with('success', "Order {$order->order_number} sent to the kiosk.");
    }

    public function receive(Request $request, Order $order): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request);

        if ($order->farmer_profile_id !== $farmer->id) {
            abort(403);
        }

        $this->orders->receive($order, $request->user());

        return back()->with('success', 'Marked as received.');
    }

    public function review(Request $request, Order $order): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request);

        if ($order->farmer_profile_id !== $farmer->id) {
            abort(403);
        }

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->orders->review($order, $request->user(), $validated['rating'], $validated['comment'] ?? null);
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['rating' => $exception->getMessage()]);
        }

        return back()->with('success', 'Thanks for the review.');
    }

    private function resolveFarmer(Request $request): FarmerProfile
    {
        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }
}
