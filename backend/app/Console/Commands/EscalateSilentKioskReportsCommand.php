<?php

namespace App\Console\Commands;

use App\Services\KioskReportService;
use Illuminate\Console\Command;

class EscalateSilentKioskReportsCommand extends Command
{
    protected $signature = 'marketplace:escalate-silent-reports';

    protected $description = 'Move kiosk reports whose supplier stayed silent past their response window to admin';

    public function handle(KioskReportService $reports): int
    {
        $moved = $reports->escalateSilent();

        $this->info("Escalated {$moved} silent report(s) to admin.");

        return self::SUCCESS;
    }
}
