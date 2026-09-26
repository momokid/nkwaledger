<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

class CloseUnconfirmedOrdersCommand extends Command
{
    protected $signature = 'marketplace:close-unconfirmed-orders';

    protected $description = 'Close marketplace orders that missed both taps within the confirmation window';

    public function handle(OrderService $orders): int
    {
        $closed = $orders->closeUnconfirmed();

        $this->info("Closed {$closed} unconfirmed order(s).");

        return self::SUCCESS;
    }
}
