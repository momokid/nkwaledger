<?php

namespace App\Services;

use App\Models\LedgerAccount;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// the buyer-order path for a produce listing - Step 5's two-tap gate mirrored with
// seller and buyer swapped: the farmer is the seller, so their confirm plays the role
// a kiosk supplier's confirm played there, and the buyer's receive is the other tap
class ProduceSaleService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
        private readonly ProduceListingService $listings,
    ) {}

    public function submit(
        ProduceListing $listing,
        User $buyer,
        string $quantity,
        string $paymentMethod,
        string $amount,
        ?int $agentId = null,
    ): ProduceSale {
        if (! $listing->isActive()) {
            throw new InvalidArgumentException('This listing is not available right now.');
        }

        if ($listing->farmerProfile?->user_id === $buyer->id) {
            throw new InvalidArgumentException('You cannot buy your own produce listing.');
        }

        if ((float) $quantity > (float) $listing->quantity_remaining) {
            throw new InvalidArgumentException('That is more than is still listed for sale.');
        }

        return DB::transaction(function () use ($listing, $buyer, $quantity, $paymentMethod, $amount, $agentId) {
            $sale = ProduceSale::create([
                'produce_listing_id' => $listing->id,
                'buyer_user_id' => $buyer->id,
                'farmer_profile_id' => $listing->farmer_profile_id,
                'quantity' => $quantity,
                'payment_method' => $paymentMethod,
                'amount_minor' => Money::toMinor($amount),
                'agent_id' => $agentId,
                'requested_at' => now(),
            ]);

            if ($listing->farmerProfile?->user !== null) {
                $this->notifications->send(
                    $listing->farmerProfile->user,
                    'marketplace.produce_sale_requested',
                    'A buyer wants to buy some of your listed produce.',
                );
            }

            $this->audit->recordOn('produce_sale.requested', $sale);

            return $sale->fresh();
        });
    }

    // the farmer's tap - accepting the buyer's offer as it stands. Locked exactly like
    // ProduceListingService::create()/markSold(), so a double-tap or a retry can never
    // both pass the "already confirmed?" guard before either commits
    public function confirm(ProduceSale $sale, User $actingUser): ProduceSale
    {
        return DB::transaction(function () use ($sale) {
            $locked = ProduceSale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($locked->closed_at !== null || $locked->confirmed_at !== null) {
                return $locked;
            }

            $locked->confirmed_at = now();
            $locked->recomputeStatus();
            $locked->save();

            $this->maybeSettle($locked->fresh());

            return $locked->fresh();
        });
    }

    // the buyer's tap - confirming they received the produce. Same locked-then-check
    // shape as confirm() above
    public function receive(ProduceSale $sale, User $actingUser): ProduceSale
    {
        return DB::transaction(function () use ($sale) {
            $locked = ProduceSale::query()->lockForUpdate()->findOrFail($sale->id);

            if ($locked->closed_at !== null || $locked->received_at !== null) {
                return $locked;
            }

            $locked->received_at = now();
            $locked->recomputeStatus();
            $locked->save();

            $this->maybeSettle($locked->fresh());

            return $locked->fresh();
        });
    }

    // an agent vouching for the sale - only this flips countsTowardCredit(), never
    // admin, who only ever needs visibility (see the Step 7 audit)
    public function coConfirm(ProduceSale $sale, User $agent): ProduceSale
    {
        if ($sale->agent_co_confirmed_at !== null) {
            return $sale;
        }

        $sale->update([
            'agent_id' => $sale->agent_id ?? $agent->id,
            'agent_co_confirmed_at' => now(),
        ]);

        $this->audit->recordOn('produce_sale.agent_co_confirmed', $sale);

        return $sale->fresh();
    }

    public function closeUnconfirmed(): int
    {
        $days = $this->settings->getInt('marketplace.buyer_confirmation_days');
        $cutoff = now()->subDays($days);

        $stale = ProduceSale::query()
            ->whereNull('closed_at')
            ->where(fn($query) => $query->whereNull('confirmed_at')->orWhereNull('received_at'))
            ->where('requested_at', '<=', $cutoff)
            ->get();

        foreach ($stale as $sale) {
            $sale->closed_at = now();
            $sale->recomputeStatus();
            $sale->save();

            $this->audit->recordOn('produce_sale.closed', $sale);
        }

        return $stale->count();
    }

    // both taps landed - post as the farmer's income, only once (guarded by
    // ledger_transaction_id, same as Step 5's Order)
    private function maybeSettle(ProduceSale $sale): void
    {
        if (! $sale->isFullyConfirmed() || $sale->ledger_transaction_id !== null) {
            return;
        }

        $listing = $sale->produceListing()->with('farmUnitStock.farmUnit.farmType.category')->first();

        $slug = match ($listing->farmUnitStock?->farmUnit?->farmType?->category?->name) {
            'Livestock' => 'animal_sale',
            'Aquatic' => 'fish_sale',
            default => 'produce_sale',
        };

        $template = TransactionTemplate::query()->where('slug', $slug)->firstOrFail();

        $settlementAccountName = $sale->payment_method->value === 'bank' ? 'Bank A/C' : 'Cash A/C';
        $settlementAccount = LedgerAccount::query()->where('name', $settlementAccountName)->firstOrFail();

        $transaction = $this->posting->post(new PostingRequest(
            farmerProfileId: $sale->farmer_profile_id,
            transactionTemplateId: $template->id,
            amount: Money::toDecimal($sale->amount_minor),
            settlementAccountId: $settlementAccount->id,
            transactionDate: now()->toDateString(),
            farmUnitId: $listing->farmUnitStock->farm_unit_id,
            narration: "Produce sale on listing {$listing->uuid}",
            recordedBy: $sale->farmerProfile->user_id,
            idempotencyKey: "produce_sale.{$sale->id}.ledger",
            quantitySold: (string) $sale->quantity,
        ));

        $sale->update(['ledger_transaction_id' => $transaction->id]);

        $this->listings->reduceRemaining($listing, (float) $sale->quantity);
    }
}
