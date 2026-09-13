<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Http\Requests\DiseaseReports\StoreDiseaseReportRequest;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Services\DiseaseReports\ReportRoutingService;
use App\Support\PhotoUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseReportController extends Controller
{
    public function __construct(private readonly ReportRoutingService $routing) {}

    public function create(Request $request, FarmUnit $farmUnit): Response
    {
        $this->resolveFarmer($request, $farmUnit);

        return Inertia::render('DiseaseReports/Create', [
            'farmUnit' => [
                'id' => $farmUnit->id,
                'name' => $farmUnit->name,
            ],
        ]);
    }

    public function store(StoreDiseaseReportRequest $request, FarmUnit $farmUnit): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request, $farmUnit);

        [$category, $role] = $this->routing->routeFor($farmUnit);
        $officer = $this->routing->officerFor($farmer, $role);

        $photoPath = PhotoUpload::store($request->file('photo'), 'disease-reports');

        DiseaseReport::create([
            'farm_unit_id' => $farmUnit->id,
            'farmer_profile_id' => $farmer->id,
            'reported_by' => null,
            'category' => $category,
            'routed_role' => $role,
            'assigned_officer_id' => $officer?->id,
            'description' => $request->validated('description'),
            'photo_path' => $photoPath,
        ]);

        return redirect()
            ->route('my-farm.index')
            ->with('success', 'Thank you. Your report has been sent.');
    }

    // only the farmer who owns this unit may report a problem on it
    private function resolveFarmer(Request $request, FarmUnit $farmUnit): FarmerProfile
    {
        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($farmer === null || $farmUnit->farmer_profile_id !== $farmer->id, 404);

        return $farmer;
    }
}
