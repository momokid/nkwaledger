<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;
use RuntimeException;

class SeedDemoData extends Command
{
    protected $signature = 'demo:seed {--fresh-only : Wipe demo data and stop, without reseeding}';

    protected $description = 'Wipe and reseed local/dev demo data (farmers, suppliers, kiosks, transactions)';

    public function handle(): int
    {
        $this->guardAgainstProduction();

        $started = microtime(true);
        $seeder = app(DemoDataSeeder::class)->setCommand($this);

        if ($this->option('fresh-only')) {
            $seeder->wipe();
            $seconds = round(microtime(true) - $started, 1);
            $this->info("Demo data wiped in {$seconds}s.");

            return self::SUCCESS;
        }

        $seeder->run();

        $seconds = round(microtime(true) - $started, 1);
        $this->info("Demo data seeded in {$seconds}s.");

        return self::SUCCESS;
    }

    // the very first thing this command does - never runs in production, no exceptions
    public function guardAgainstProduction(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demo data seeding is not permitted in production.');
        }
    }
}
