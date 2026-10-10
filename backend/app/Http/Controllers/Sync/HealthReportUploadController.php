<?php

namespace App\Http\Controllers\Sync;

use App\Exceptions\HealthUploadRefused;
use App\Http\Controllers\Controller;
use App\Models\DiseaseReport;
use App\Services\DiseaseReports\HealthReportUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HealthReportUploadController extends Controller
{
    public function __construct(private readonly HealthReportUploadService $uploads) {}

    public function show(Request $request, string $uuid, string $kind): JsonResponse
    {
        return $this->run(fn(DiseaseReport $report) => $this->uploads->state($report, $kind), $request, $uuid);
    }

    public function open(Request $request, string $uuid, string $kind): JsonResponse
    {
        $data = $request->validate(['size' => ['required', 'integer'], 'sha256' => ['required', 'regex:/^[0-9a-f]{64}$/']]);

        return $this->run(
            fn(DiseaseReport $report) => $this->uploads->open($request->user(), $report, $kind, (int) $data['size'], $data['sha256']),
            $request,
            $uuid,
        );
    }

    public function chunk(Request $request, string $uuid, string $kind): JsonResponse
    {
        $offset = $request->header('X-Upload-Offset');

        return $this->run(
            fn(DiseaseReport $report) => $this->uploads->chunk($report, $kind, ctype_digit((string) $offset) ? (int) $offset : -1, $request->getContent()),
            $request,
            $uuid,
        );
    }

    private function run(callable $step, Request $request, string $uuid): JsonResponse
    {
        $report = $this->uploads->reportFor($request->user(), $uuid);

        abort_if($report === null, 404);

        try {
            return response()->json($step($report));
        } catch (HealthUploadRefused $refused) {
            return response()->json(['error' => $refused->getMessage()] + $refused->extra, $refused->status);
        }
    }
}
