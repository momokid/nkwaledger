<?php

namespace App\Http\Controllers\Supplier;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $supplier = $request->user()->supplier;

        return Inertia::render('Supplier/Dashboard', [
            'has_profile' => $supplier !== null,
            'business_name' => $supplier?->business_name,
            'verification_status' => $supplier?->verificationStatus(),
            'kiosks_count' => $supplier?->kiosks()->count() ?? 0,
        ]);
    }
}
