<?php

namespace App\Console\Commands;

use App\Services\Loans\LoanUseReminderService;
use Illuminate\Console\Command;

class RemindLoanUseCommand extends Command
{
    protected $signature = 'loans:remind-use';

    protected $description = 'Remind farmers to record how they used a loan, after 7 days and then every 14 days until it is cleared';

    public function handle(LoanUseReminderService $reminders): int
    {
        $this->info("Sent {$reminders->sendDue()} loan reminder(s).");

        return self::SUCCESS;
    }
}
