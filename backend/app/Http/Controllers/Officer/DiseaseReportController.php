<?php

namespace App\Http\Controllers\Officer;

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiseaseReports\RespondToDiseaseReportRequest;
use App\Http\Requests\DiseaseReports\SetOfficerCategoryRequest;
use App\Models\HealthCategory;
use App\Models\DiseaseReport;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseReportController extends Controller
{
    public function __construct(private readonly NotificationService $notifications) {}

    public function dashboard(Request $request): Response
    {
        $all = DiseaseReport::query()
            ->where('assigned_officer_id', $request->user()->id)
            ->with(['farmUnit', 'farmerProfile.user', 'officerHealthCategory', 'healthCategory'])
            ->orderByDesc('created_at')
            ->get();

        // the trends page links here with ?category={health category uuid} or "none"
        $category = $request->query('category');
        $listed = $category === null ? $all : $all->filter(
            fn(DiseaseReport $report) => ($report->effectiveHealthCategory()?->uuid ?? 'none') === $category,
        );

        return Inertia::render($this->dashboardPage($request), [
            'reports' => $listed->values()->map(fn(DiseaseReport $report) => $this->summarise($report)),
            'categoryName' => $category === null ? null : ($category === 'none'
                ? 'Not yet categorised'
                : HealthCategory::query()->where('uuid', $category)->value('name')),
            'counts' => [
                'new' => $all->where('status', DiseaseReportStatus::New)->count(),
                'reviewed' => $all->where('status', DiseaseReportStatus::Reviewed)->count(),
                'resolved' => $all->where('status', DiseaseReportStatus::Resolved)->count(),
                'recent' => $all->where('created_at', '>=', now()->subDays(90))->count(),
            ],
            'field' => $request->user()->hasRole('vet') ? 'livestock' : 'crops',
            'basePath' => $request->user()->hasRole('vet') ? '/vet' : '/adviser',
        ]);
    }

    public function show(Request $request, DiseaseReport $report): Response
    {
        $this->guardOfficer($request, $report);

        $report->load(['farmUnit.farmType', 'farmerProfile.user', 'healthCategory', 'officerHealthCategory']);

        $history = DiseaseReport::query()
            ->historyFor($report->farm_unit_id, $report->id)
            ->get()
            ->map(fn(DiseaseReport $past) => [
                'category' => $past->category,
                'status' => $past->status->value,
                'description' => $past->description,
                'created_at' => $past->created_at->toDateString(),
            ]);

        return Inertia::render('Officer/ReportShow', [
            'report' => $this->detail($request, $report),
            'history' => $history,
            'categories' => HealthCategory::query()
                ->activeFor($report->farmUnit->farmType->category)
                ->get()
                ->map(fn(HealthCategory $c) => ['uuid' => $c->uuid, 'name' => $c->name]),
            'basePath' => $request->user()->hasRole('vet') ? '/vet' : '/adviser',
        ]);
    }

    public function respond(RespondToDiseaseReportRequest $request, DiseaseReport $report): RedirectResponse
    {
        $this->guardOfficer($request, $report);

        $report->respond(
            DiseaseReportStatus::from($request->validated('status')),
            ContactMethod::from($request->validated('contact_method')),
            $request->validated('note'),
        );

        $this->notifyResponse($report->fresh(['farmUnit', 'farmerProfile.user', 'farmerProfile.assignedAgent']));

        return back()->with('success', 'Your response has been saved.');
    }

    public function setCategory(SetOfficerCategoryRequest $request, DiseaseReport $report): RedirectResponse
    {
        $this->guardOfficer($request, $report);

        $report->setOfficerCategory($request->category(), $request->boolean('rejected'));

        return back()->with('success', 'Category saved.');
    }

    private function notifyResponse(DiseaseReport $report): void
    {
        $statusLabel = match ($report->status) {
            DiseaseReportStatus::Reviewed => 'reviewed',
            DiseaseReportStatus::Resolved => 'resolved',
            DiseaseReportStatus::New => 'new',
        };

        $farmer = $report->farmerProfile;
        $unitName = $report->farmUnit?->name;

        if ($farmer?->user) {
            $this->notifications->send(
                $farmer->user,
                'disease_report.responded',
                "Your report on {$unitName} has been marked {$statusLabel}.",
                '/my-farm/reports',
            );
        }

        if ($farmer?->assignedAgent) {
            $farmerName = trim("{$farmer->user?->surname} {$farmer->user?->first_name}");

            $this->notifications->send(
                $farmer->assignedAgent,
                'disease_report.responded',
                "A report for {$farmerName}'s {$unitName} was marked {$statusLabel}.",
                "/agent/farmers/{$farmer->uuid}",
            );
        }
    }

    // an officer may only open or respond to a report actually routed to them
    private function guardOfficer(Request $request, DiseaseReport $report): void
    {
        abort_if($report->assigned_officer_id !== $request->user()->id, 404);
    }

    private function dashboardPage(Request $request): string
    {
        return $request->user()->hasRole('vet') ? 'Vet/Dashboard' : 'Adviser/Dashboard';
    }

    private function summarise(DiseaseReport $report): array
    {
        return [
            'uuid' => $report->uuid,
            'farm_unit_name' => $report->farmUnit?->name,
            'farmer_name' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
            'category' => $report->category,
            'status' => $report->status->value,
            'created_at' => $report->created_at->toDateString(),
            'ai_ready' => $report->ai_status === 'ready',
        ];
    }

    private function detail(Request $request, DiseaseReport $report): array
    {
        return [
            'uuid' => $report->uuid,
            'farm_unit_name' => $report->farmUnit?->name,
            'farmer_name' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
            'category' => $report->category,
            'status' => $report->status->value,
            'description' => $report->description,
            // built from the request, not config('app.url'), so it matches whatever
            // host the browser actually used (localhost, LAN IP, staging domain, ...)
            'photo_url' => $report->mediaUrl('photo', $request),
            'audio_url' => $report->mediaUrl('audio', $request),
            'contact_method' => $report->contact_method?->value,
            'response_note' => $report->response_note,
            'created_at' => $report->created_at->toDateString(),
            'ai' => $this->aiDraft($report),
            'officer_category_uuid' => $report->officerHealthCategory?->uuid,
        ];
    }

    // the category and note show only for a confident draft; urgent shows whatever the confidence
    private function aiDraft(DiseaseReport $report): array
    {
        $ready = $report->ai_status === 'ready';

        return [
            'category_uuid' => $ready ? $report->healthCategory?->uuid : null,
            'category' => $ready ? $report->healthCategory?->name : null,
            'note' => $ready ? $report->ai_note : null,
            'urgent' => $report->ai_urgent,
        ];
    }
}
