<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use App\Models\KioskReport;
use App\Services\KioskReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KioskReportController extends Controller
{
    public function __construct(private readonly KioskReportService $reports) {}

    public function answer(Request $request, KioskReport $kioskReport): RedirectResponse
    {
        $supplier = $request->user()->supplier;

        if ($supplier === null || $kioskReport->kiosk->supplier_id !== $supplier->id) {
            abort(403);
        }

        $validated = $request->validate([
            'supplier_answer' => ['required', 'string', 'max:2000'],
        ]);

        $this->reports->answer($kioskReport, $validated['supplier_answer']);

        return back()->with('success', 'Your answer has been sent to admin.');
    }
}
