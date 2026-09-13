<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use Inertia\Inertia;
use Inertia\Response;

class DiseaseReportQueueController extends Controller
{
    // reports whose farmer's agent has no officer of the matching role linked yet
    public function index(): Response
    {
        $reports = DiseaseReport::query()
            ->awaitingOfficer()
            ->with(['farmUnit', 'farmerProfile.user'])
            ->orderBy('created_at')
            ->get()
            ->map(fn(DiseaseReport $report) => [
                'uuid' => $report->uuid,
                'farm_unit_name' => $report->farmUnit?->name,
                'farmer_name' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
                'category' => $report->category,
                'routed_role' => $report->routed_role->value,
                'created_at' => $report->created_at->toDateString(),
            ]);

        return Inertia::render('Admin/DiseaseReports/Index', [
            'reports' => $reports,
        ]);
    }
}
