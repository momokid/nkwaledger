<?php

namespace App\Console\Commands;

use App\Services\ProduceListingService;
use Illuminate\Console\Command;

class ProduceListingsStillAvailableCommand extends Command
{
    protected $signature = 'marketplace:prompt-still-available-listings';

    protected $description = 'Prompt non-expiring listings for a still-available check, then hide the ones nobody answered';

    public function handle(ProduceListingService $listings): int
    {
        $prompted = $listings->promptStillAvailable();
        $hidden = $listings->hideSilentListings();

        $this->info("Prompted {$prompted} listing(s), hid {$hidden} silent listing(s).");

        return self::SUCCESS;
    }
}
