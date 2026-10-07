<?php

namespace App\Http\Controllers;

use App\Models\DiseaseReport;
use App\Services\AccessControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Response;

// a report's private photo and voice note: the farmer, the officer it went to, and an admin of the queue; 404 to everyone else
class DiseaseReportMediaController extends Controller
{
    public function __construct(private readonly AccessControlService $access) {}

    public function show(Request $request, DiseaseReport $report, string $kind): Response
    {
        $user = $request->user();
        $path = $kind === 'photo' ? $report->photo_path : $report->audio_path;

        $mayView = $report->farmerProfile?->user_id === $user->id
            || $report->assigned_officer_id === $user->id
            || $this->access->can($user, 'disease-reports.manage');

        abort_unless($path !== null && $mayView, 404);

        $disk = Storage::disk($report->media_disk);

        return response($disk->get($path), 200, ['Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream']);
    }
}
