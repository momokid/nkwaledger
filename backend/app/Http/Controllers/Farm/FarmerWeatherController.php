<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\WeatherAdvisoryLog;
use App\Services\Weather\WeatherAdvisor;
use App\Services\Weather\WeatherService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FarmerWeatherController extends Controller
{
    public function __construct(
        private readonly WeatherService $weather,
        private readonly WeatherAdvisor $advisor,
    ) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        return Inertia::render('MyWeather/Index', [
            'locations' => $this->locationsFor($farmer->id),
        ]);
    }

    // one card per community, not per farm unit — two units on the same land
    // share the same sky, so they share the same forecast
    private function locationsFor(int $farmerId): array
    {
        $units = FarmUnit::query()
            ->where('farmer_profile_id', $farmerId)
            ->with(['community', 'farmType.category'])
            ->get();

        return $units
            ->groupBy('community_id')
            ->map(function ($group) {
                $community = $group->first()->community;

                $categories = $group
                    ->pluck('farmType.category.name')
                    ->filter()
                    ->unique()
                    ->values();

                $farmTypes = $group
                    ->pluck('farmType.name')
                    ->filter()
                    ->unique()
                    ->values();

                $snapshot = $this->weather->forCommunity($community);
                $forecast = $this->weather->extendedForecastFor($community);

                if (!$snapshot->available || $forecast === null) {
                    return [
                        'community' => $community->name,
                        'available' => false,
                    ];
                }

                $alerts = $farmTypes
                    ->map(fn($farmType) => [
                        'farm_type' => $farmType,
                        'message' => $this->advisor->alertFor($snapshot->condition, $farmType),
                    ])
                    ->filter(fn($alert) => $alert['message'] !== null)
                    ->values()
                    ->all();

                // on an ordinary day there's no risk to warn about, so tell the
                // farmer what the sky is actually doing instead of a generic line
                $today = $forecast[0] ?? null;
                $headline = ($snapshot->condition === 'normal' && $today !== null && $today['weather_code'] !== null)
                    ? $this->advisor->describeDay($today['weather_code'], (float) $today['temperature_max_c'])
                    : $this->advisor->headline($snapshot->condition);

                return [
                    'community' => $community->name,
                    'available' => true,
                    'headline' => $headline,
                    'advice' => $categories->map(fn($category) => [
                        'category' => $category,
                        'message' => $this->advisor->adviceFor($snapshot->condition, $category),
                    ])->values()->all(),
                    'alerts' => $alerts,
                    'outlook' => $this->advisor->outlookFor($forecast),
                    'forecast' => $forecast,
                    'history' => $this->historyFor($community->id),
                ];
            })
            ->values()
            ->all();
    }

    // most recent day first, capped at 2 weeks — this only starts filling in
    // from the day the snapshot command shipped, so it's empty at first
    private function historyFor(int $communityId): array
    {
        return WeatherAdvisoryLog::where('community_id', $communityId)
            ->orderByDesc('date')
            ->limit(14)
            ->get()
            ->map(fn($log) => [
                'date' => $log->date->toDateString(),
                'condition' => $log->condition,
                'headline' => $log->headline,
            ])
            ->values()
            ->all();
    }

    // the farmer's own page names nobody, and only their own profile answers
    private function resolveFarmer(Request $request): FarmerProfile
    {
        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }
}
