<?php

namespace App\Services;

use App\Enums\CommissionStatus;
use App\Enums\OrderEventType;
use App\Models\Commission;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use App\Models\LedgerAccount;
use App\Models\Order;
use App\Models\Review;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// the whole cart-to-ledger lifecycle for one kiosk order: submit, either party's tap,
// review, and the scheduled auto-close - one place owns the two-tap gate so it is only
// ever evaluated once, the same way regardless of which tap lands second
class OrderService
{
    private const INPUT_PURCHASE_SLUG = 'input_purchase';

    public function __construct(
        private readonly PostingService $posting,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    /**
     * @param array<int, array{kiosk_product_id: int, quantity: int}> $items
     */
    public function submitCart(
        FarmerProfile $farmer,
        Kiosk $kiosk,
        array $items,
        string $paymentMethod,
        int $farmUnitId,
        ?int $facilitatingAgentId = null,
    ): Order {
        if ($items === []) {
            throw new InvalidArgumentException('Add at least one product to the cart.');
        }

        if ($kiosk->supplier?->user_id === $farmer->user_id) {
            throw new InvalidArgumentException('You cannot order from your own kiosk.');
        }

        return DB::transaction(function () use ($farmer, $kiosk, $items, $paymentMethod, $farmUnitId, $facilitatingAgentId) {
            $order = Order::create([
                'kiosk_id' => $kiosk->id,
                'farmer_profile_id' => $farmer->id,
                'farm_unit_id' => $farmUnitId,
                'facilitating_agent_id' => $facilitatingAgentId,
                'payment_method' => $paymentMethod,
                'requested_at' => now(),
            ]);

            $amountMinor = 0;

            foreach ($items as $item) {
                // every line has to belong to this same kiosk and still be available -
                // the cart holds one kiosk per order, per spec
                $kioskProduct = KioskProduct::query()
                    ->available()
                    ->where('kiosk_id', $kiosk->id)
                    ->findOrFail($item['kiosk_product_id']);

                $lineTotal = $kioskProduct->price * (int) $item['quantity'];
                $amountMinor += $lineTotal;

                $order->items()->create([
                    'kiosk_product_id' => $kioskProduct->id,
                    'quantity' => $item['quantity'],
                    'unit_price_at_order_time' => $kioskProduct->price,
                ]);
            }

            $order->update(['amount_minor' => $amountMinor]);

            $this->recordEvent($order, OrderEventType::Requested, $farmer->user);

            if ($facilitatingAgentId !== null) {
                Commission::create([
                    'order_id' => $order->id,
                    'agent_id' => $facilitatingAgentId,
                    'status' => CommissionStatus::PendingAdmin,
                    'verifies_farmer' => $farmer->assigned_agent_id === $facilitatingAgentId,
                ]);
            }

            if ($kiosk->supplier?->user !== null) {
                $this->notifications->send(
                    $kiosk->supplier->user,
                    'marketplace.order_requested',
                    "New order {$order->order_number} on \"{$kiosk->name}\".",
                );
            }

            $this->audit->recordOn('marketplace_order.requested', $order);

            return $order->fresh();
        });
    }

    // the supplier's tap - a no-op once the order has already closed or was already
    // confirmed, so a late or repeated tap changes nothing
    public function confirm(Order $order, User $actingUser): Order
    {
        if ($order->closed_at !== null || $order->confirmed_at !== null) {
            return $order;
        }

        $order->confirmed_at = now();
        $order->recomputeStatus();
        $order->save();

        $this->recordEvent($order, OrderEventType::Confirmed, $actingUser);
        $this->maybeSettle($order->fresh());

        return $order->fresh();
    }

    // the farmer's tap - same no-op guard as confirm()
    public function receive(Order $order, User $actingUser): Order
    {
        if ($order->closed_at !== null || $order->received_at !== null) {
            return $order;
        }

        $order->received_at = now();
        $order->recomputeStatus();
        $order->save();

        $this->recordEvent($order, OrderEventType::Received, $actingUser);
        $this->maybeSettle($order->fresh());

        return $order->fresh();
    }

    public function review(Order $order, User $actingUser, int $rating, ?string $comment = null): Review
    {
        if (! $order->isFullyConfirmed()) {
            throw new InvalidArgumentException('This order is not confirmed and received yet.');
        }

        if ($order->kiosk->supplier?->user_id === $actingUser->id) {
            throw new InvalidArgumentException('A supplier cannot review their own order.');
        }

        if ($order->review()->exists()) {
            throw new InvalidArgumentException('This order already has a review.');
        }

        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('A rating must be between 1 and 5.');
        }

        $review = $order->review()->create([
            'rating' => $rating,
            'comment' => $comment,
        ]);

        $this->audit->recordOn('marketplace_order.reviewed', $order);

        return $review;
    }

    // orders that never got both taps within the settings-driven window close
    // automatically and never post, review, or count toward sales - called by the
    // scheduled command
    public function closeUnconfirmed(): int
    {
        $days = $this->settings->getInt('marketplace.buyer_confirmation_days');
        $cutoff = now()->subDays($days);

        $stale = Order::query()
            ->whereNull('closed_at')
            ->where(fn($query) => $query->whereNull('confirmed_at')->orWhereNull('received_at'))
            ->where('requested_at', '<=', $cutoff)
            ->get();

        foreach ($stale as $order) {
            $order->closed_at = now();
            $order->recomputeStatus();
            $order->save();

            $this->recordEvent($order, OrderEventType::Closed, null);
            $this->audit->recordOn('marketplace_order.closed', $order);
        }

        return $stale->count();
    }

    // both taps landed - post to the farmer's ledger, but only once (guarded by
    // ledger_transaction_id and by PostingService's own idempotency key)
    private function maybeSettle(Order $order): void
    {
        if (! $order->isFullyConfirmed() || $order->ledger_transaction_id !== null) {
            return;
        }

        $template = TransactionTemplate::query()->where('slug', self::INPUT_PURCHASE_SLUG)->firstOrFail();

        // both payment methods are real payment events by the time "Received" fires, so
        // this always settles against a real cash/bank account, never Accounts Payable -
        // never provisional, never on credit
        $settlementAccountName = $order->payment_method->value === 'bank' ? 'Bank A/C' : 'Cash A/C';
        $settlementAccount = LedgerAccount::query()->where('name', $settlementAccountName)->firstOrFail();

        $transaction = $this->posting->post(new PostingRequest(
            farmerProfileId: $order->farmer_profile_id,
            transactionTemplateId: $template->id,
            amount: Money::toDecimal($order->amount_minor),
            settlementAccountId: $settlementAccount->id,
            transactionDate: now()->toDateString(),
            farmUnitId: $order->farm_unit_id,
            narration: "Marketplace order {$order->order_number}",
            recordedBy: $order->farmerProfile->user_id,
            idempotencyKey: "marketplace_order.{$order->id}.ledger",
        ));

        $order->update(['ledger_transaction_id' => $transaction->id]);
    }

    private function recordEvent(Order $order, OrderEventType $type, ?User $actor): void
    {
        $order->events()->create([
            'actor_user_id' => $actor?->id,
            'event_type' => $type,
            'occurred_at' => now(),
        ]);
    }
}
