<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
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

                $snapshot = $this->weather->forCommunity($community);
                $forecast = $this->weather->extendedForecastFor($community);

                if (!$snapshot->available || $forecast === null) {
                    return [
                        'community' => $community->name,
                        'available' => false,
                    ];
                }

                return [
                    'community' => $community->name,
                    'available' => true,
                    'headline' => $this->advisor->headline($snapshot->condition),
                    'advice' => $categories->map(fn($category) => [
                        'category' => $category,
                        'message' => $this->advisor->adviceFor($snapshot->condition, $category),
                    ])->values()->all(),
                    'forecast' => $forecast,
                ];
            })
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
