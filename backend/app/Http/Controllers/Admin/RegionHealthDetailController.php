<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

class RegionHealthDetailController extends Controller
{
    public function show(Region $region): JsonResponse
    {
        $reports = DiseaseReport::query()
            ->whereHas('farmerProfile.community.district', fn($query) => $query->where('region_id', $region->id))
            ->with(['farmerProfile.user:id,surname,first_name'])
            ->latest('created_at')
            ->get();

        return response()->json([
            'region_name' => $region->name,
            'reports' => $reports->map(fn($report) => [
                'uuid' => $report->uuid,
                'farmer_name' => trim(
                    "{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}",
                ),
                'category' => $report->category,
                'status' => $report->status->value,
                'description' => $report->description,
                'created_at' => $report->created_at->toDateString(),
            ])->values()->all(),
        ]);
    }
}
