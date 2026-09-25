<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KioskReport;
use App\Services\AccessControlService;
use App\Services\KioskReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class KioskReportController extends Controller
{
    public function __construct(
        private readonly KioskReportService $reports,
        private readonly AccessControlService $access,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Admin/Marketplace/KioskReports/Index', [
            'reports' => KioskReport::query()
                ->with(['kiosk.supplier', 'farmerProfile.user'])
                ->when($request->input('status'), fn($query, $status) => $query->where('status', $status))
                ->orderByDesc('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn(KioskReport $report) => [
                    'uuid' => $report->uuid,
                    'kiosk_uuid' => $report->kiosk->uuid,
                    'kiosk_name' => $report->kiosk->name,
                    'urgent' => $report->kiosk->hasUrgentReports(),
                    'reporter' => trim("{$report->farmerProfile?->user?->surname} {$report->farmerProfile?->user?->first_name}"),
                    'reason' => $report->reason,
                    'status' => $report->status->value,
                    'supplier_due_at' => $report->supplier_due_at,
                    'admin_due_at' => $report->admin_due_at,
                ]),
            'filters' => $request->only(['status']),
            'permissions' => [
                'manage' => $this->access->can($user, 'marketplace-kiosks.suspend'),
            ],
        ]);
    }

    public function resolve(Request $request, KioskReport $kioskReport): RedirectResponse
    {
        $this->reports->resolve($kioskReport, $request->user());

        return back()->with('success', 'Report marked as resolved.');
    }

    public function extend(KioskReport $kioskReport): RedirectResponse
    {
        $this->reports->extendAdminWindow($kioskReport);

        return back()->with('success', 'Admin contact window extended.');
    }
}
