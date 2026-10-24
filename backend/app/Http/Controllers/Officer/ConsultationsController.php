<?php

namespace App\Http\Controllers\Officer;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// the reports this officer has answered, newest answer first
class ConsultationsController extends Controller
{
    public function index(Request $request): Response
    {
        $consultations = DiseaseReport::query()
            ->where('assigned_officer_id', $request->user()->id)
            ->whereNotNull('responded_at')
            ->with(['farmUnit', 'farmerProfile.user'])
            ->orderByDesc('responded_at')
            ->get()
            ->map(fn(DiseaseReport $report) => [
                'uuid' => $report->uuid,
                'farm_unit_name' => $report->farmUnit?->name,
                'farmer_name' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
                'category' => $report->category,
                'status' => $report->status->value,
                'contact_method' => $report->contact_method?->value,
                'note' => Str::limit(Str::before((string) $report->response_note, "\n"), 120),
                'responded_at' => $report->responded_at->toDateString(),
            ]);

        return Inertia::render('Officer/Consultations', [
            'consultations' => $consultations,
            'basePath' => $request->user()->hasRole('vet') ? '/vet' : '/adviser',
        ]);
    }
}
