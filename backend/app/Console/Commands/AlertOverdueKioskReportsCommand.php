<?php

namespace App\Console\Commands;

use App\Services\KioskReportService;
use Illuminate\Console\Command;

class AlertOverdueKioskReportsCommand extends Command
{
    protected $signature = 'marketplace:alert-overdue-reports';

    protected $description = "Alert admin that a report's contact window has ended unresolved - never suspends automatically";

    public function handle(KioskReportService $reports): int
    {
        $alerted = $reports->alertOverdue();

        $this->info("Alerted admin on {$alerted} overdue report(s).");

        return self::SUCCESS;
    }
}
