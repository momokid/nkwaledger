<?php

namespace App\Console\Commands;

use App\Models\Community;
use App\Models\WeatherAdvisoryLog;
use App\Services\Weather\WeatherAdvisor;
use App\Services\Weather\WeatherService;
use Illuminate\Console\Command;

class SnapshotWeatherAdvisories extends Command
{
    protected $signature = 'weather:snapshot-advisories';

    protected $description = 'Record today\'s weather condition for every community that has a farm unit, building up advisory history over time';

    public function handle(WeatherService $weather, WeatherAdvisor $advisor): int
    {
        $today = now()->toDateString();

        $communities = Community::query()
            ->whereHas('farmUnits')
            ->get();

        foreach ($communities as $community) {
            if (WeatherAdvisoryLog::where('community_id', $community->id)->whereDate('date', $today)->exists()) {
                continue;
            }

            $snapshot = $weather->forCommunity($community);

            if (!$snapshot->available) {
                continue;
            }

            $headline = ($snapshot->condition === 'normal' && $snapshot->weatherCode !== null)
                ? $advisor->describeDay($snapshot->weatherCode, (float) ($snapshot->temperatureMaxC ?? 0))
                : $advisor->headline($snapshot->condition);

            WeatherAdvisoryLog::create([
                'community_id' => $community->id,
                'date' => $today,
                'condition' => $snapshot->condition,
                'headline' => $headline,
            ]);
        }

        return self::SUCCESS;
    }
}
