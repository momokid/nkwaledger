<?php

namespace App\Http\Controllers\Farm;

use App\Enums\OfficerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiseaseReports\StoreDiseaseReportRequest;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use App\Services\DiseaseReports\ReportRoutingService;
use App\Services\NotificationService;
use App\Support\PhotoUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseReportController extends Controller
{
    public function __construct(
        private readonly ReportRoutingService $routing,
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $reports = DiseaseReport::query()
            ->where('farmer_profile_id', $farmer->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(DiseaseReport $report) => [
                'uuid' => $report->uuid,
                'category' => $report->category,
                'status' => $report->status->value,
                'created_at' => $report->created_at->toDateString(),
            ]);

        return Inertia::render('DiseaseReports/Index', [
            'reports' => $reports,
        ]);
    }

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

        $report = DiseaseReport::create([
            'farm_unit_id' => $farmUnit->id,
            'farmer_profile_id' => $farmer->id,
            'reported_by' => null,
            'category' => $category,
            'routed_role' => $role,
            'assigned_officer_id' => $officer?->id,
            'description' => $request->validated('description'),
            'photo_path' => $photoPath,
        ]);

        $this->notifySubmission($report, $request->user(), $farmer, $farmUnit, $officer, $role);

        return redirect()
            ->route('my-farm.index')
            ->with('success', 'Thank you. Your report has been sent.');
    }

    // only the farmer who owns this unit may report a problem on it
    private function resolveFarmer(Request $request, ?FarmUnit $farmUnit = null): FarmerProfile
    {
        $farmer = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($farmer === null, 404);

        if ($farmUnit !== null) {
            abort_if($farmUnit->farmer_profile_id !== $farmer->id, 404);
        }

        return $farmer;
    }

    private function notifySubmission(
        DiseaseReport $report,
        User $farmerUser,
        FarmerProfile $farmer,
        FarmUnit $farmUnit,
        ?User $officer,
        OfficerRole $role,
    ): void {
        $this->notifications->send(
            $farmerUser,
            'disease_report.submitted',
            'Your report has been sent.',
            '/my-farm/reports',
        );

        if ($farmer->assignedAgent) {
            $farmerName = trim("{$farmerUser->surname} {$farmerUser->first_name}");

            $this->notifications->send(
                $farmer->assignedAgent,
                'disease_report.submitted',
                "A new report was submitted for {$farmerName}'s {$farmUnit->name}.",
                "/agent/farmers/{$farmer->uuid}",
            );
        }

        if ($officer) {
            $this->notifications->send(
                $officer,
                'disease_report.submitted',
                "A new {$report->category} report needs your attention.",
                $role === OfficerRole::Vet
                    ? "/vet/reports/{$report->uuid}"
                    : "/adviser/reports/{$report->uuid}",
            );
        }
    }
}
