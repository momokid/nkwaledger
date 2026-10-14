<?php

namespace App\Services\DiseaseReports;

use App\Enums\DiseaseReportStatus;
use App\Enums\OfficerRole;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

// the moment a report reaches people: routed and told about, exactly once
class ReportReleaseService
{
    public function __construct(
        private readonly ReportRoutingService $routing,
        private readonly NotificationService $notifications,
    ) {}

    // a waiting report whose photo is now stored; false when it was already released
    public function release(int $reportId, string $photoPath, string $disk): bool
    {
        $released = DB::transaction(function () use ($reportId, $photoPath, $disk) {
            $report = DiseaseReport::withoutGlobalScopes()->lockForUpdate()->findOrFail($reportId);

            if ($report->photo_path !== null || $report->status !== DiseaseReportStatus::WaitingForPhoto) {
                return null;
            }

            $farmer = $report->farmerProfile;
            $officer = $this->routing->officerFor($farmer, $report->routed_role);

            $report->forceFill([
                'photo_path' => $photoPath,
                'media_disk' => $disk,
                'status' => DiseaseReportStatus::New,
                'assigned_officer_id' => $officer?->id,
            ])->save();

            $this->notifySubmission($report, $farmer->user, $farmer, $report->farmUnit, $officer, $report->routed_role);

            return $report;
        });

        if ($released === null) {
            Storage::disk($disk)->delete($photoPath);
        }

        return $released !== null;
    }

    public function notifySubmission(
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
