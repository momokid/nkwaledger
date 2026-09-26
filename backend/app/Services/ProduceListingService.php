<?php

namespace App\Services;

use App\Enums\ProduceListingStatus;
use App\Models\FarmerProfile;
use App\Models\FarmUnitStock;
use App\Models\ProduceListing;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

// a listing's whole lifecycle except the buyer-order path (see ProduceSaleService for
// that): posting under a locked stock-cap check, an agent-posted draft's farmer
// agreement, withdrawal, the direct "Mark Sold" settlement, and the scheduled
// reconciliation/expiry/still-available jobs
class ProduceListingService
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly NotificationService $notifications,
        private readonly SettingsService $settings,
        private readonly AuditService $audit,
    ) {}

    public function create(
        FarmUnitStock $stock,
        FarmerProfile $farmer,
        User $postedBy,
        float $quantity,
        ?int $cropExpiryDays,
        ?string $photo,
    ): ProduceListing {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Please list at least some quantity.');
        }

        return DB::transaction(function () use ($stock, $farmer, $postedBy, $quantity, $cropExpiryDays, $photo) {
            // locked for the lifetime of this transaction - a second listing attempt on
            // the same batch has to wait here, so the two can never jointly oversell it
            $locked = FarmUnitStock::query()->lockForUpdate()->findOrFail($stock->id);

            $this->assertWithinCapacity($locked, $quantity);

            $isAgentPosted = $postedBy->hasRole('agent');

            $listing = ProduceListing::create([
                'farm_unit_stock_id' => $locked->id,
                'farmer_profile_id' => $farmer->id,
                'posted_by_user_id' => $postedBy->id,
                'status' => $isAgentPosted ? ProduceListingStatus::Draft : ProduceListingStatus::Active,
                'quantity_listed' => $quantity,
                'quantity_remaining' => $quantity,
                'photo' => $photo,
                'crop_expiry_days' => $cropExpiryDays,
                'expires_at' => $cropExpiryDays !== null ? now()->addDays($cropExpiryDays) : null,
                'farmer_agreed_at' => $isAgentPosted ? null : now(),
            ]);

            if ($isAgentPosted && $farmer->user !== null) {
                $this->notifications->send(
                    $farmer->user,
                    'marketplace.listing_awaiting_agreement',
                    'Your agent listed some of your produce for sale - review it and agree before it goes live.',
                );
            }

            $this->audit->recordOn('produce_listing.created', $listing);

            return $listing->fresh();
        });
    }

    // the same lock-then-check used by create(), reused so a stock-cap test can prove
    // two attempts on the same batch can never jointly exceed it
    public function assertWithinCapacity(FarmUnitStock $stock, float $quantity, ?int $excludingListingId = null): void
    {
        $claimed = (float) ProduceListing::query()
            ->where('farm_unit_stock_id', $stock->id)
            ->where('status', ProduceListingStatus::Active)
            ->when($excludingListingId !== null, fn($query) => $query->where('id', '!=', $excludingListingId))
            ->sum('quantity_remaining');

        $available = (float) $stock->current_quantity - $claimed;

        if ($quantity > $available) {
            throw new InvalidArgumentException("Only {$available} is left to list from this batch.");
        }
    }

    public function agree(ProduceListing $listing): ProduceListing
    {
        if (! $listing->isDraft()) {
            return $listing;
        }

        $listing->update([
            'status' => ProduceListingStatus::Active,
            'farmer_agreed_at' => now(),
        ]);

        $this->audit->recordOn('produce_listing.agreed', $listing);

        return $listing->fresh();
    }

    public function withdraw(ProduceListing $listing): ProduceListing
    {
        if (in_array($listing->status, [ProduceListingStatus::Sold, ProduceListingStatus::Withdrawn], true)) {
            return $listing;
        }

        $listing->update(['status' => ProduceListingStatus::Withdrawn]);
        $this->audit->recordOn('produce_listing.withdrawn', $listing);

        return $listing->fresh();
    }

    // the farmer-initiated path: no buyer, no payment method, no agent co-confirm -
    // the farmer types the amount themselves, the app never multiplies price by quantity
    public function markSold(
        ProduceListing $listing,
        string $amount,
        string $quantity,
        int $settlementAccountId,
        int $recordedBy,
    ): Transaction {
        return DB::transaction(function () use ($listing, $amount, $quantity, $settlementAccountId, $recordedBy) {
            $locked = ProduceListing::query()->lockForUpdate()->findOrFail($listing->id);

            if ((float) $quantity > (float) $locked->quantity_remaining) {
                throw new InvalidArgumentException('That is more than is still listed for sale.');
            }

            $template = TransactionTemplate::query()->where('slug', $this->saleTemplateSlug($locked))->firstOrFail();

            $transaction = $this->posting->post(new PostingRequest(
                farmerProfileId: $locked->farmer_profile_id,
                transactionTemplateId: $template->id,
                amount: $amount,
                settlementAccountId: $settlementAccountId,
                transactionDate: now()->toDateString(),
                farmUnitId: $locked->farmUnitStock->farm_unit_id,
                narration: "Produce listing {$locked->uuid} - direct sale",
                recordedBy: $recordedBy,
                quantitySold: $quantity,
            ));

            $this->reduceRemaining($locked, (float) $quantity);
            $this->audit->recordOn('produce_listing.marked_sold', $locked);

            return $transaction;
        });
    }

    // shared with ProduceSaleService once a buyer-order sale settles too, so both
    // paths close the listing at zero the same way. Locks the row itself rather than
    // trusting every caller to have already locked it - markSold() already holds this
    // same row's lock when it calls in, so this nests via a savepoint (Laravel handles
    // that transparently) and re-acquires a lock the transaction already owns, which is
    // a harmless no-op rather than a second, competing lock; a caller that has NOT
    // already locked the row (ProduceSaleService::maybeSettle(), which only read it
    // for display fields) is exactly who this protects
    public function reduceRemaining(ProduceListing $listing, float $quantity): void
    {
        DB::transaction(function () use ($listing, $quantity) {
            $locked = ProduceListing::query()->lockForUpdate()->findOrFail($listing->id);

            $remaining = round((float) $locked->quantity_remaining - $quantity, 2);
            $locked->quantity_remaining = max($remaining, 0);

            if ($locked->quantity_remaining <= 0) {
                $locked->status = ProduceListingStatus::Sold;
            }

            $locked->save();
        });
    }

    // crop, livestock or fish - which produce-sale template a batch's listing settles
    // against follows straight from its farm type's category, same mapping
    // TransactionTemplateSeeder already uses
    private function saleTemplateSlug(ProduceListing $listing): string
    {
        return match ($listing->farmUnitStock?->farmUnit?->farmType?->category?->name) {
            'Livestock' => 'animal_sale',
            'Aquatic' => 'fish_sale',
            default => 'produce_sale',
        };
    }

    // stock can shrink after a listing is already live (a loss, a correction); this is
    // what keeps a listing from ever offering more than the batch actually still has -
    // run periodically rather than wired into every place FarmUnitStock's count can
    // change, matching how every other marketplace reconciliation in this codebase
    // (stale prices, expiring products) is a scheduled sweep, not an event listener
    public function reconcileStock(): int
    {
        $affected = 0;

        ProduceListing::active()->chunkById(100, function ($listings) use (&$affected) {
            foreach ($listings as $listing) {
                DB::transaction(function () use ($listing, &$affected) {
                    $locked = FarmUnitStock::query()->lockForUpdate()->findOrFail($listing->farm_unit_stock_id);

                    $claimedByOthers = (float) ProduceListing::query()
                        ->where('farm_unit_stock_id', $locked->id)
                        ->where('status', ProduceListingStatus::Active)
                        ->where('id', '!=', $listing->id)
                        ->sum('quantity_remaining');

                    $available = max((float) $locked->current_quantity - $claimedByOthers, 0);

                    if ((float) $listing->quantity_remaining > $available) {
                        $listing->quantity_remaining = $available;
                        $listing->save();

                        if ($listing->farmerProfile?->user !== null) {
                            $this->notifications->send(
                                $listing->farmerProfile->user,
                                'marketplace.listing_reduced',
                                'Your stock count went down, so we reduced how much of a listing is still for sale.',
                            );
                        }

                        $affected++;
                    }
                });
            }
        });

        return $affected;
    }

    public function sendExpiryReminders(): int
    {
        $days = $this->settings->getInt('marketplace.crop_listing_reminder_days');

        $listings = ProduceListing::active()
            ->whereNotNull('expires_at')
            ->whereNull('expiry_reminder_sent_at')
            ->where('expires_at', '<=', now()->addDays($days))
            ->get();

        foreach ($listings as $listing) {
            if ($listing->postedBy !== null) {
                $this->notifications->send(
                    $listing->postedBy,
                    'marketplace.listing_expiring_soon',
                    'A produce listing is about to expire.',
                );
            }

            $listing->update(['expiry_reminder_sent_at' => now()]);
        }

        return $listings->count();
    }

    public function expireDueCrops(): int
    {
        $due = ProduceListing::active()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($due as $listing) {
            $listing->update(['status' => ProduceListingStatus::Expired]);
        }

        return $due->count();
    }

    // only non-expiring listings get this prompt - a crop listing already has its own
    // expiry path above, so silence there means nothing extra needs to happen
    public function promptStillAvailable(): int
    {
        $days = $this->settings->getInt('marketplace.animal_listing_prompt_days');

        $listings = ProduceListing::active()
            ->whereNull('expires_at')
            ->whereNull('still_available_prompted_at')
            ->where('created_at', '<=', now()->subDays($days))
            ->get();

        foreach ($listings as $listing) {
            if ($listing->postedBy !== null) {
                $this->notifications->send(
                    $listing->postedBy,
                    'marketplace.listing_still_available',
                    'Is this produce listing still available?',
                );
            }

            $listing->update(['still_available_prompted_at' => now()]);
        }

        return $listings->count();
    }

    public function hideSilentListings(): int
    {
        $days = $this->settings->getInt('marketplace.animal_listing_prompt_days');

        $listings = ProduceListing::active()
            ->whereNotNull('still_available_prompted_at')
            ->where('still_available_prompted_at', '<=', now()->subDays($days))
            ->get();

        foreach ($listings as $listing) {
            $listing->update(['status' => ProduceListingStatus::Hidden]);
        }

        return $listings->count();
    }
}
