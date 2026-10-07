<?php

namespace App\Http\Controllers\Farm;

use App\Enums\OfficerRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiseaseReports\StoreDiseaseReportRequest;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\SyncSubmission;
use App\Models\User;
use App\Services\DiseaseReports\ReportRoutingService;
use App\Services\NotificationService;
use App\Services\RecordLock;
use App\Support\AudioUpload;
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
        private readonly RecordLock $lock,
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

    public function show(Request $request, DiseaseReport $report): Response
    {
        $farmer = $this->resolveFarmer($request);

        // read-only, and only the farmer's own - not the agent's, not another farmer's
        abort_if($report->farmer_profile_id !== $farmer->id, 403);

        $report->load('farmUnit');

        return Inertia::render('DiseaseReports/Show', [
            'report' => [
                'uuid' => $report->uuid,
                'farm_unit_name' => $report->farmUnit?->name,
                'category' => $report->category,
                'status' => $report->status->value,
                'description' => $report->description,
                'photo_url' => $request->getSchemeAndHttpHost() . '/storage/' . $report->photo_path,
                'audio_url' => $report->audio_path
                    ? $request->getSchemeAndHttpHost() . '/storage/' . $report->audio_path
                    : null,
                'contact_method' => $report->contact_method?->value,
                'response_note' => $report->response_note,
                'created_at' => $report->created_at->toDateString(),
            ],
        ]);
    }

    public function create(Request $request, FarmUnit $farmUnit): Response
    {
        $farmer = $this->resolveFarmer($request, $farmUnit);

        return Inertia::render('DiseaseReports/Create', [
            'farmUnit' => [
                'id' => $farmUnit->id,
                'name' => $farmUnit->name,
            ],
            'farmer' => ['id' => $farmer->uuid],
        ]);
    }

    public function store(StoreDiseaseReportRequest $request, FarmUnit $farmUnit): RedirectResponse
    {
        $farmer = $this->resolveFarmer($request, $farmUnit);
        $user = $request->user();
        $key = $request->validated('idempotency_key');

        // the phone may already have sent this same report through sync: then there is nothing to make
        $created = $this->lock->around(
            $user->id,
            $key,
            fn() => $this->arrivedBySync($user, $key) ? null : $this->createReport($request, $farmer, $farmUnit, $key),
        );

        if ($created !== null) {
            [$report, $officer, $role] = $created;

            $this->notifySubmission($report, $user, $farmer, $farmUnit, $officer, $role);
        }

        return redirect()
            ->route('my-farm.index')
            ->with('success', 'Thank you. Your report has been sent.');
    }

    private function arrivedBySync(User $user, ?string $key): bool
    {
        return $key !== null && SyncSubmission::where('client_uuid', $key)
            ->where('user_id', $user->id)
            ->where('type', SyncSubmission::TYPE_HEALTH_REPORT)
            ->exists();
    }

    private function createReport(StoreDiseaseReportRequest $request, FarmerProfile $farmer, FarmUnit $farmUnit, ?string $key): array
    {
        [$category, $role] = $this->routing->routeFor($farmUnit);
        $officer = $this->routing->officerFor($farmer, $role);

        $photoPath = PhotoUpload::store($request->file('photo'), 'disease-reports');

        $audioPath = $request->hasFile('audio')
            ? AudioUpload::store($request->file('audio'), 'disease-reports')
            : null;

        $report = DiseaseReport::create([
            'client_uuid' => $key,
            'farm_unit_id' => $farmUnit->id,
            'farmer_profile_id' => $farmer->id,
            'reported_by' => null,
            'category' => $category,
            'routed_role' => $role,
            'assigned_officer_id' => $officer?->id,
            'description' => $request->validated('description'),
            'photo_path' => $photoPath,
            'audio_path' => $audioPath,
        ]);

        return [$report, $officer, $role];
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
