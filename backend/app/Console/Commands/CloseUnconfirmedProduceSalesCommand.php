<?php

namespace App\Console\Commands;

use App\Services\ProduceSaleService;
use Illuminate\Console\Command;

class CloseUnconfirmedProduceSalesCommand extends Command
{
    protected $signature = 'marketplace:close-unconfirmed-produce-sales';

    protected $description = 'Close produce sales that missed both taps within the confirmation window';

    public function handle(ProduceSaleService $sales): int
    {
        $closed = $sales->closeUnconfirmed();

        $this->info("Closed {$closed} unconfirmed produce sale(s).");

        return self::SUCCESS;
    }
}
