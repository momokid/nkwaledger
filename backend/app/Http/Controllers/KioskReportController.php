<?php

namespace App\Http\Controllers;

use App\Models\Kiosk;
use App\Services\KioskReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KioskReportController extends Controller
{
    public function __construct(private readonly KioskReportService $reports) {}

    public function store(Request $request, Kiosk $kiosk): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);

        $farmer = $request->user()->farmerProfile;

        $this->reports->submit($farmer, $kiosk, $validated['reason'], $validated['details'] ?? null);

        return back()->with('success', 'Your report has been sent to the supplier and to admin.');
    }
}
