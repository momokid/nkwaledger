<?php

namespace App\Console\Commands;

use App\Services\Ledger\CreditReminderService;
use Illuminate\Console\Command;

class RemindOutstandingCreditCommand extends Command
{
    protected $signature = 'credit:remind';

    protected $description = 'Remind farmers about credit sales/purchases still outstanding after three days, repeating every three days until settled';

    public function handle(CreditReminderService $reminders): int
    {
        $sent = $reminders->sendDue();

        $this->info("Sent {$sent} credit reminder(s).");

        return self::SUCCESS;
    }
}
