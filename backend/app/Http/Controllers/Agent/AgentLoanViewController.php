<?php

namespace App\Http\Controllers\Agent;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Services\Loans\AdminLoanListService;
use App\Services\Loans\LoanCheckService;
use App\Services\Loans\LoanListService;
use App\Services\Loans\RepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// the agent's Loans menu: the loans of the agent's own farmers, read-only. The lender check is the admin's to save
class AgentLoanViewController extends Controller
{
    public function __construct(
        private readonly AdminLoanListService $list,
        private readonly LoanListService $loans,
        private readonly RepaymentService $repayments,
        private readonly LoanCheckService $checks,
    ) {}

    public function index(Request $request): JsonResponse|Response
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:' . implode(',', AdminLoanListService::STATUSES)],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($data['search'] ?? ''));
        $status = $data['status'] ?? 'all';
        $agentId = $request->user()->id;
        $payload = $this->list->page($search, $status, (int) ($data['page'] ?? 1), $agentId) + ['total_owed' => $this->list->totalOwed($agentId)];

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return Inertia::render('Agent/Loans/Index', $payload + ['filters' => ['search' => $search, 'status' => $status]]);
    }

    public function show(Loan $loan): Response
    {
        Gate::authorize('viewAsAgent', $loan);

        return Inertia::render('Agent/Loans/Show', [
            'loan' => $this->repayments->summary($loan),
            'farmer' => $this->list->farmerName($loan->farmerProfile),
            'repayments' => $this->loans->repaymentRows($loan),
            'rejected' => $this->loans->rejectedRepayments($loan),
            'checks' => $this->checks->history($loan),
        ]);
    }
}
