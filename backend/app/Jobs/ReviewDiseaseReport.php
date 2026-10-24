<?php

namespace App\Jobs;

use App\Models\DiseaseReport;
use App\Models\HealthCategory;
use App\Services\DiseaseReports\HealthImageReviewer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

// draft review for the officer only; whatever goes wrong here ends as ai_status 'failed' and never touches the report
class ReviewDiseaseReport implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $reportId) {}

    public function uniqueId(): string
    {
        return (string) $this->reportId;
    }

    // a queue that is down must not fail the report that was just saved
    public static function send(int $reportId): void
    {
        try {
            static::dispatch($reportId)->afterCommit();
        } catch (Throwable $e) {
            Log::warning('Health review not queued', ['error' => $e::class]);
        }
    }

    public function handle(HealthImageReviewer $reviewer): void
    {
        if (blank(config('services.openrouter.key'))) {
            return;
        }

        $report = DiseaseReport::withoutGlobalScopes()->find($this->reportId);

        if ($report === null || $report->photo_path === null) {
            return;
        }

        try {
            $review = $this->ask($reviewer, $report);
        } catch (Throwable $e) {
            Log::warning('Health review failed', ['error' => $e::class]);
            $review = null;
        }

        if ($review === null) {
            $report->forceFill(['ai_status' => 'failed', 'ai_reviewed_at' => now()])->save();

            return;
        }

        [$review, $category] = $review;

        $status = ! $review->photoUsable ? 'unusable'
            : ($review->confidence >= config('services.openrouter.min_confidence') ? 'ready' : 'low_confidence');

        $report->forceFill([
            'ai_status' => $status,
            'health_category_id' => $review->photoUsable ? $category?->id : null,
            'ai_confidence' => $review->photoUsable ? $review->confidence : null,
            'ai_urgent' => $review->urgent,
            'ai_note' => $review->note,
            'ai_model' => $review->model,
            'ai_reviewed_at' => now(),
        ])->save();
    }

    private function ask(HealthImageReviewer $reviewer, DiseaseReport $report): ?array
    {
        $disk = Storage::disk($report->media_disk);
        $bytes = $disk->get($report->photo_path);

        if ($bytes === null) {
            return null;
        }

        $categories = HealthCategory::query()
            ->activeFor($report->farmUnit->farmType->category)
            ->get();

        $review = $reviewer->review(
            $bytes,
            (new \finfo(FILEINFO_MIME_TYPE))->buffer($bytes) ?: 'image/jpeg',
            (string) $report->description,
            $report->category,
            $categories->pluck('name')->all(),
        );

        return $review === null ? null : [$review, $categories->firstWhere('name', $review->category)];
    }
}
