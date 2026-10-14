<?php

namespace App\Console\Commands;

use App\Enums\DiseaseReportStatus;
use App\Models\DiseaseReport;
use App\Models\HealthReportUpload;
use App\Models\SyncSubmission;
use App\Services\AuditService;
use App\Services\DiseaseReports\HealthReportUploadService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeWaitingHealthReports extends Command
{
    protected $signature = 'health-reports:purge-waiting';

    protected $description = 'Remove health reports whose photo never arrived, and upload sessions that went stale';

    public function handle(AuditService $audit, HealthReportUploadService $uploads): int
    {
        $days = config('health_reports.waiting_max_age_days');
        $removed = 0;

        DiseaseReport::withoutGlobalScopes()
            ->where('status', DiseaseReportStatus::WaitingForPhoto->value)
            ->where('created_at', '<', now()->subDays($days))
            ->each(function (DiseaseReport $report) use ($uploads, &$removed) {
                DB::transaction(function () use ($report, $uploads) {
                    HealthReportUpload::where('disease_report_id', $report->id)->each(fn(HealthReportUpload $session) => $uploads->discard($session));

                    if ($report->audio_path !== null) {
                        Storage::disk($report->media_disk)->delete($report->audio_path);
                    }

                    // the phone's record stays, pointing at nothing: that is how the phone learns the report is gone
                    SyncSubmission::where('disease_report_id', $report->id)->update(['disease_report_id' => null]);
                    $report->delete();
                });

                $removed++;
            });

        HealthReportUpload::where('expires_at', '<', now())->each(fn(HealthReportUpload $session) => $uploads->discard($session));

        $audit->record('health_report.waiting_purged', ['count' => $removed, 'older_than_days' => $days]);

        $this->info("Removed {$removed} waiting report(s).");

        return self::SUCCESS;
    }
}
