<?php

namespace App\Http\Controllers\Admin;

use App\Enums\KioskStatus;
use App\Http\Controllers\Controller;
use App\Models\Kiosk;
use App\Models\KioskReport;
use App\Services\AccessControlService;
use App\Services\AuditService;
use App\Services\KioskReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KioskController extends Controller
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly AuditService $audit,
        private readonly KioskReportService $reports,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Marketplace/Kiosks/Index', [
            'kiosks' => Kiosk::query()
                ->with('supplier:id,business_name,email')
                ->orderByDesc('id')
                ->paginate(15)
                ->through(fn(Kiosk $kiosk) => [
                    'uuid' => $kiosk->uuid,
                    'kiosk_number' => $kiosk->kiosk_number,
                    'name' => $kiosk->name,
                    'supplier' => $kiosk->supplier?->business_name,
                    'status' => $kiosk->status->value,
                    'confirmed_at' => $kiosk->confirmed_at,
                    'requires_admin_approval' => $kiosk->requires_admin_approval,
                    'admin_approved_at' => $kiosk->admin_approved_at,
                ]),
            'permissions' => [
                'approve' => $this->access->can($user, 'marketplace-kiosks.approve'),
                'suspend' => $this->access->can($user, 'marketplace-kiosks.suspend'),
            ],
        ]);
    }

    public function approve(Request $request, Kiosk $kiosk): RedirectResponse
    {
        $kiosk->admin_approved_at = now();
        $kiosk->admin_approved_by = $request->user()->id;

        if ($kiosk->isFullyConfirmed()) {
            $kiosk->status = KioskStatus::Active;
        }

        $kiosk->save();

        $this->audit->recordOn('marketplace_kiosk.approved', $kiosk);

        return back()->with('success', "{$kiosk->name} is approved.");
    }

    public function suspend(Kiosk $kiosk): RedirectResponse
    {
        $kiosk->update(['status' => KioskStatus::Suspended]);

        KioskReport::query()
            ->where('kiosk_id', $kiosk->id)
            ->whereIn('status', ['open', 'supplier_answered', 'with_admin'])
            ->get()
            ->each(fn(KioskReport $report) => $this->reports->markSuspended($report));

        $this->audit->recordOn('marketplace_kiosk.suspended', $kiosk);

        return back()->with('success', "{$kiosk->name} is suspended.");
    }

    public function restore(Kiosk $kiosk): RedirectResponse
    {
        $kiosk->update(['status' => $kiosk->isFullyConfirmed() ? KioskStatus::Active : KioskStatus::PendingConfirmation]);

        $this->audit->recordOn('marketplace_kiosk.restored', $kiosk);

        return back()->with('success', "{$kiosk->name} is restored.");
    }
}
