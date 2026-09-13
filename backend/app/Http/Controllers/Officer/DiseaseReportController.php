<?php

namespace App\Http\Controllers\Officer;

use App\Enums\ContactMethod;
use App\Enums\DiseaseReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\DiseaseReports\RespondToDiseaseReportRequest;
use App\Models\DiseaseReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseReportController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $reports = DiseaseReport::query()
            ->where('assigned_officer_id', $request->user()->id)
            ->with(['farmUnit', 'farmerProfile.user'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn(DiseaseReport $report) => $this->summarise($report));

        return Inertia::render($this->dashboardPage($request), [
            'reports' => $reports,
        ]);
    }

    public function show(Request $request, DiseaseReport $report): Response
    {
        $this->guardOfficer($request, $report);

        $report->load(['farmUnit', 'farmerProfile.user']);

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
            'report' => $this->detail($report),
            'history' => $history,
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

        return back()->with('success', 'Your response has been saved.');
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
        ];
    }

    private function detail(DiseaseReport $report): array
    {
        return [
            'uuid' => $report->uuid,
            'farm_unit_name' => $report->farmUnit?->name,
            'farmer_name' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
            'category' => $report->category,
            'status' => $report->status->value,
            'description' => $report->description,
            'photo_url' => Storage::disk('public')->url($report->photo_path),
            'contact_method' => $report->contact_method?->value,
            'response_note' => $report->response_note,
            'created_at' => $report->created_at->toDateString(),
        ];
    }
}
