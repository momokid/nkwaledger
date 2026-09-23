<?php

namespace App\Services;

use App\Mail\ProductExpiringMail;
use App\Models\KioskProduct;
use Illuminate\Support\Facades\Mail;

class ProductExpiryAlertService
{
    public function __construct(private readonly SettingsService $settings) {}

    // one alert per product, the alert-days setting before its expiry date - never
    // after it has already expired, and never twice for the same expiry date
    public function sendDue(): int
    {
        $alertDays = $this->settings->getInt('marketplace.product_expiry_alert_days');
        $today = now()->toDateString();
        $horizon = now()->addDays($alertDays)->toDateString();

        $sent = 0;

        KioskProduct::query()
            ->whereNull('expiry_alerted_at')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [$today, $horizon])
            ->with(['catalogProduct', 'kiosk.supplier'])
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

        if ($supplier === null) {
            return false;
        }

        $name = $product->catalogProduct->name;
        $date = $product->expiry_date->format('d M Y');
        $message = "\"{$name}\" at {$product->kiosk->name} expires on {$date}.";

        Mail::to($supplier->email)->send(new ProductExpiringMail($message));

        $product->update(['expiry_alerted_at' => now()]);

        return true;
    }
}
