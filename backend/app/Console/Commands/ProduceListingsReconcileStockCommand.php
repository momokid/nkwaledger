<?php

namespace App\Console\Commands;

use App\Services\ProduceListingService;
use Illuminate\Console\Command;

class ProduceListingsReconcileStockCommand extends Command
{
    protected $signature = 'marketplace:reconcile-listing-stock';

    protected $description = 'Reduce any listing that now offers more than its batch actually has left';

    public function handle(ProduceListingService $listings): int
    {
        $affected = $listings->reconcileStock();

        $this->info("Reduced {$affected} listing(s) to match their batch's stock.");

        return self::SUCCESS;
    }
}
