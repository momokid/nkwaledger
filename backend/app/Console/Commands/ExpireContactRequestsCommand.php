<?php

namespace App\Console\Commands;

use App\Services\ContactRequestService;
use Illuminate\Console\Command;

class ExpireContactRequestsCommand extends Command
{
    protected $signature = 'marketplace:expire-contact-requests';

    protected $description = 'Move contact requests past their reply window to Expired so nothing on them can be revealed';

    public function handle(ContactRequestService $contactRequests): int
    {
        $expired = $contactRequests->expireOverdue();

        $this->info("Expired {$expired} contact request(s).");

        return self::SUCCESS;
    }
}
