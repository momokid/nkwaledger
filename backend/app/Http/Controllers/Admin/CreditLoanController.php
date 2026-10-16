<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exceptions\Ledger\PostingFailed;
use App\Models\Loan;
use App\Models\LoanCheck;
use App\Services\Loans\LoanCheckService;
use App\Support\Wording;
use Illuminate\Support\Str;
use App\Services\Loans\AdminLoanListService;
use App\Services\Loans\LoanListService;
use App\Services\Loans\RepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// the admin's Credit menu: every farmer's loans, read-only. The admin never reaches a farmer's own pages from here
class CreditLoanController extends Controller
{
    public function __construct(
        private readonly AdminLoanListService $list,
        private readonly LoanListService $loans,
        private readonly RepaymentService $repayments,
        private readonly LoanCheckService $checks,
    ) {}

    // the first visit renders the page with the first page in it; every search, filter and page after that is fetched in place
    public function index(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:' . implode(',', AdminLoanListService::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $status = $data['status'] ?? 'all';
        $payload = $this->list->page($search, $status, (int) ($data['page'] ?? 1)) + ['total_owed' => $this->list->totalOwed()];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Admin/Loans/Index', $payload + ['filters' => ['search' => $search, 'status' => $status]]);
    }

    public function show(Loan $loan): Response
    {
        Gate::authorize('view', $loan);

        return Inertia::render('Admin/Loans/Show', [
            'loan' => $this->repayments->summary($loan),
            'farmer' => $this->list->farmerName($loan->farmerProfile),
            'repayments' => $this->loans->repaymentRows($loan),
            'rejected' => $this->loans->rejectedRepayments($loan),
            'checks' => $this->checks->history($loan),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    // saved in place: the page gets back the current check and the history, and nothing reloads
    public function check(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('view', $loan);

        $words = Wording::FALLBACK;
        $data = $request->validate([
            'status' => ['required', 'string', 'in:' . implode(',', LoanCheck::STATUSES)],
            'note' => ['nullable', 'string', 'max:500'],
            'idempotency_key' => ['nullable', 'string', 'max:64'],
        ], [
            'status.required' => $words, 'status.string' => $words, 'status.in' => $words,
            'note.string' => $words, 'note.max' => $words,
        ]);

        try {
            $this->checks->record($loan, $request->user(), $data['status'], $data['note'] ?? null, $data['idempotency_key'] ?? null);
        } catch (PostingFailed $failure) {
            return response()->json(['message' => $failure->getMessage()], 422);
        }

        return response()->json([
            'check' => LoanCheckService::present($loan->currentCheck()->first()),
            'checks' => $this->checks->history($loan),
        ], 201);
    }
}