<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;

class GeocodeCommunities extends Command
{
    protected $signature = 'weather:geocode-communities';

    protected $description = 'Fill in latitude/longitude for every community that does not have coordinates yet';

    public function handle(WeatherService $weather): int
    {
        $communities = Community::query()
            ->whereNull('latitude')
            ->orWhereNull('longitude')
            ->get();

        if ($communities->isEmpty()) {
            $this->info('Every community already has coordinates.');

            return self::SUCCESS;
        }

        $this->info("Geocoding {$communities->count()} communities...");

        $bar = $this->output->createProgressBar($communities->count());
        $failed = [];

        foreach ($communities as $community) {
            $weather->ensureCoordinates($community);

            if ($community->fresh()->latitude === null) {
                $failed[] = $community->name;
            }

            $bar->advance();

            // a small pause so this stays a polite, non-bursty caller of a free public api
            usleep(300000);
        }

        $bar->finish();
        $this->newLine(2);

        if ($failed !== []) {
            $this->warn('Could not resolve coordinates for: ' . implode(', ', $failed));
        }

        $this->info('Done.');

        return self::SUCCESS;
    }
}
