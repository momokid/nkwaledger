<?php

namespace App\Console\Commands;

use App\Services\ProduceListingService;
use Illuminate\Console\Command;

class ProduceListingsExpireCropsCommand extends Command
{
    protected $signature = 'marketplace:expire-crop-listings';

    protected $description = 'Remind farmers of crop listings about to expire, then expire the ones past their date';

    public function handle(ProduceListingService $listings): int
    {
        $reminded = $listings->sendExpiryReminders();
        $expired = $listings->expireDueCrops();

        $this->info("Reminded {$reminded} listing(s), expired {$expired} listing(s).");

        return self::SUCCESS;
    }
}
