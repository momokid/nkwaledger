<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Loans\LoanAnalysisService;
use App\Support\Wording;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

// the admin's Loan Analysis. The first visit renders the page with the first table in it; changing the grouping or the dates is fetched in place
class LoanAnalysisController extends Controller
{
    public function __construct(private readonly LoanAnalysisService $analysis) {}

    public function index(Request $request): JsonResponse|Response
    {
        $words = Wording::FALLBACK;
        $data = $request->validate([
            'group' => ['nullable', 'in:' . implode(',', LoanAnalysisService::GROUPS)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ], [
            'group.in' => $words, 'from.date_format' => $words, 'to.date_format' => $words, 'to.after_or_equal' => $words,
        ]);

        $group = $data['group'] ?? 'region';
        $payload = $this->analysis->analyse($group, $data['from'] ?? null, $data['to'] ?? null);

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/LoanAnalysis/Index', $payload + ['group' => $group, 'from' => $data['from'] ?? '', 'to' => $data['to'] ?? '']);
    }
}