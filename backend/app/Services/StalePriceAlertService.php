<?php

namespace App\Services;

use App\Mail\StalePriceMail;
use App\Models\KioskProduct;
use Illuminate\Support\Facades\Mail;

class StalePriceAlertService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
    ) {}

    // sends once per stale period - a product already alerted stays quiet until its
    // price is confirmed or changed, which clears the flag and lets it fire again
    public function sendDue(): int
    {
        $staleDays = $this->settings->getInt('marketplace.price_stale_days');
        $cutoff = now()->subDays($staleDays);

        $sent = 0;

        KioskProduct::query()
            ->whereNull('stale_alerted_at')
            ->where(function ($query) use ($cutoff) {
                $query->whereNull('price_confirmed_at')->orWhere('price_confirmed_at', '<=', $cutoff);
            })
            ->with(['catalogProduct', 'kiosk.supplier.user'])
            ->chunkById(200, function ($products) use (&$sent) {
                foreach ($products as $product) {
                    if ($this->alert($product)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    private function alert(KioskProduct $product): bool
    {
        $supplier = $product->kiosk->supplier;
        $user = $supplier?->user;

        if ($user === null) {
            return false;
        }

        $name = $product->catalogProduct->name;
        $message = "The price for \"{$name}\" at {$product->kiosk->name} has not been confirmed in a while. Tap to confirm it is still correct.";

        Mail::to($supplier->email)->send(new StalePriceMail($message));
        $this->notifications->send($user, 'marketplace.price_stale', $message);
        $this->notifications->sendToPermission('marketplace-catalog.view', 'marketplace.price_stale', "Stale price: \"{$name}\" at {$product->kiosk->name}.");

        $product->update(['stale_alerted_at' => now()]);

        return true;
    }
}
