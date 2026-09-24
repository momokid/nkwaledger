<?php

namespace App\Console\Commands;

use App\Services\StalePriceAlertService;
use Illuminate\Console\Command;

class AlertStalePricesCommand extends Command
{
    protected $signature = 'marketplace:alert-stale-prices';

    protected $description = 'Email and notify suppliers whose kiosk product prices have gone unconfirmed past the stale-price setting, and alert admin';

    public function handle(StalePriceAlertService $alerts): int
    {
        $sent = $alerts->sendDue();

        $this->info("Sent {$sent} stale price alert(s).");

        return self::SUCCESS;
    }
}
