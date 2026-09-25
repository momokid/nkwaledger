<?php

namespace App\Services;

use App\Enums\KioskReportStatus;
use App\Mail\KioskReportedMail;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskReport;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;

// the permission that already gates suspending a kiosk is reused for every admin alert
// here too, rather than adding a whole new "marketplace-reports" permission group for
// a feature that is really just another view onto the same kiosks that permission covers
class KioskReportService
{
    private const ADMIN_PERMISSION = 'marketplace-kiosks.suspend';

    public function __construct(
        private readonly SettingsService $settings,
        private readonly NotificationService $notifications,
        private readonly AuditService $audit,
    ) {}

    public function submit(FarmerProfile $farmer, Kiosk $kiosk, string $reason, ?string $details = null): KioskReport
    {
        $alreadyReported = KioskReport::query()
            ->where('kiosk_id', $kiosk->id)
            ->where('farmer_profile_id', $farmer->id)
            ->exists();

        if ($alreadyReported) {
            throw new InvalidArgumentException('You have already reported this kiosk.');
        }

        $days = $this->settings->getInt('marketplace.supplier_report_response_days');

        $report = KioskReport::create([
            'kiosk_id' => $kiosk->id,
            'farmer_profile_id' => $farmer->id,
            'reason' => $reason,
            'details' => $details,
            'status' => KioskReportStatus::Open,
            'supplier_due_at' => now()->addDays($days),
        ]);

        $this->alertOnCreate($report);

        $this->audit->record('kiosk_report.created', [
            'kiosk_id' => $kiosk->id,
            'report_id' => $report->id,
        ]);

        return $report;
    }

    public function answer(KioskReport $report, string $answer): void
    {
        if ($report->status !== KioskReportStatus::Open) {
            throw new InvalidArgumentException('This report is no longer waiting on a supplier answer.');
        }

        $report->update([
            'status' => KioskReportStatus::SupplierAnswered,
            'supplier_answer' => $answer,
            'supplier_answered_at' => now(),
        ]);

        $this->audit->record('kiosk_report.answered', ['report_id' => $report->id]);
    }

    public function resolve(KioskReport $report, User $admin): void
    {
        $report->update([
            'status' => KioskReportStatus::Resolved,
            'resolved_by' => $admin->id,
            'resolved_at' => now(),
        ]);

        $this->audit->record('kiosk_report.resolved', [
            'report_id' => $report->id,
            'resolved_by' => $admin->id,
        ]);
    }

    // called once the supplier/kiosk is actually suspended through the existing admin
    // action - this only reflects that outcome on the report, it never triggers it
    public function markSuspended(KioskReport $report): void
    {
        if (! $report->isOpenForAction()) {
            return;
        }

        $report->update(['status' => KioskReportStatus::Suspended]);
    }

    public function extendAdminWindow(KioskReport $report): void
    {
        if ($report->status !== KioskReportStatus::WithAdmin) {
            throw new InvalidArgumentException('Only a report already with admin can have its window extended.');
        }

        $days = $this->settings->getInt('marketplace.admin_intervention_days');

        $report->update([
            'admin_due_at' => now()->addDays($days),
            'admin_window_alerted_at' => null,
        ]);

        $this->audit->record('kiosk_report.admin_window_extended', ['report_id' => $report->id]);
    }

    // supplier stayed silent past their own window - moves to admin, with admin's own fresh deadline
    public function escalateSilent(): int
    {
        $days = $this->settings->getInt('marketplace.admin_intervention_days');
        $moved = 0;

        KioskReport::query()
            ->where('status', KioskReportStatus::Open)
            ->where('supplier_due_at', '<=', now())
            ->with('kiosk')
            ->chunkById(200, function ($reports) use (&$moved, $days) {
                foreach ($reports as $report) {
                    $report->update([
                        'status' => KioskReportStatus::WithAdmin,
                        'admin_due_at' => now()->addDays($days),
                    ]);

                    $this->notifications->sendToPermission(
                        self::ADMIN_PERMISSION,
                        'marketplace.report_unanswered',
                        "\"{$report->kiosk->name}\" did not answer a report in time - needs admin contact.",
                    );

                    $moved++;
                }
            });

        return $moved;
    }

    // admin's own window ran out with the report still unresolved - alert only, never
    // auto-suspend (a deliberate deviation from the spec doc's stated default assumption);
    // admin_window_alerted_at stops this firing again every day while it just sits waiting
    public function alertOverdue(): int
    {
        $alerted = 0;

        KioskReport::query()
            ->where('status', KioskReportStatus::WithAdmin)
            ->whereNotNull('admin_due_at')
            ->where('admin_due_at', '<=', now())
            ->whereNull('admin_window_alerted_at')
            ->with('kiosk')
            ->chunkById(200, function ($reports) use (&$alerted) {
                foreach ($reports as $report) {
                    $this->notifications->sendToPermission(
                        self::ADMIN_PERMISSION,
                        'marketplace.report_window_ended',
                        "The contact window for a report on \"{$report->kiosk->name}\" has ended, still unresolved. Suspend the supplier's kiosks or extend the window.",
                    );

                    $report->update(['admin_window_alerted_at' => now()]);
                    $alerted++;
                }
            });

        return $alerted;
    }

    private function alertOnCreate(KioskReport $report): void
    {
        $kiosk = $report->kiosk;
        $supplier = $kiosk->supplier;
        $message = "A farmer reported \"{$kiosk->name}\": {$report->reason}";

        if ($supplier !== null) {
            Mail::to($supplier->email)->send(new KioskReportedMail($message));

            if ($supplier->user !== null) {
                $this->notifications->send($supplier->user, 'marketplace.report_received', $message);
            }
        }

        $this->notifications->sendToPermission(self::ADMIN_PERMISSION, 'marketplace.report_received', $message);
    }
}
