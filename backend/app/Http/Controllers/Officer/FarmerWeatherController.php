<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Farm\FarmerWeatherController as OwnWeather;
use App\Models\FarmerProfile;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// a vet or adviser sees the weather only for farmers who sent them a report
class FarmerWeatherController extends Controller
{
    public function __construct(private readonly OwnWeather $weather) {}

    public function index(Request $request): Response
    {
        $farmers = $this->reportingFarmers($request)
            ->with(['user', 'farmUnits.community'])
            ->get()
            ->map(fn(FarmerProfile $farmer) => [
                'uuid' => $farmer->uuid,
                'name' => $this->nameOf($farmer),
                'community' => $farmer->farmUnits->pluck('community.name')->filter()->unique()->implode(', '),
            ])
            ->values();

        return Inertia::render('Officer/WeatherFarmers', [
            'farmers' => $farmers,
            'basePath' => $this->basePath($request),
        ]);
    }

    public function show(Request $request, string $farmerUuid): Response
    {
        $farmer = $this->reportingFarmers($request)->where('farmer_profiles.uuid', $farmerUuid)->with('user')->firstOrFail();

        return Inertia::render('MyWeather/Index', [
            'locations' => $this->weather->locationsFor($farmer->id),
            'farmerName' => $this->nameOf($farmer),
            'backHref' => $this->basePath($request) . '/weather',
        ]);
    }

    private function reportingFarmers(Request $request)
    {
        return FarmerProfile::query()->whereIn('farmer_profiles.id', function ($query) use ($request) {
            $query->select('farmer_profile_id')->from('disease_reports')
                ->where('assigned_officer_id', $request->user()->id);
        });
    }

    private function nameOf(FarmerProfile $farmer): string
    {
        return trim("{$farmer->user?->surname} {$farmer->user?->first_name}");
    }

    private function basePath(Request $request): string
    {
        return $request->user()->hasRole('vet') ? '/vet' : '/adviser';
    }
}
