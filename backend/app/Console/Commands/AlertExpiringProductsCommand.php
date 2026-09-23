<?php

namespace App\Console\Commands;

use App\Services\ProductExpiryAlertService;
use Illuminate\Console\Command;

class AlertExpiringProductsCommand extends Command
{
    protected $signature = 'marketplace:alert-expiring-products';

    protected $description = 'Email suppliers about kiosk products approaching their expiry date, per the product-expiry-alert-days setting';

    public function handle(ProductExpiryAlertService $alerts): int
    {
        $sent = $alerts->sendDue();

        $this->info("Sent {$sent} expiry alert(s).");

        return self::SUCCESS;
    }
}
