<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Loans\Concerns\PreviewsRepayment;
use App\Http\Requests\Loans\StoreRepaymentRequest;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Services\Loans\PendingEntryService;
use App\Services\Loans\RepaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

// the farmer's current agent records a repayment for the farmer. It waits for the farmer and touches nothing until then
class AgentRepaymentController extends Controller
{
    use PreviewsRepayment;

    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly PendingEntryService $entries,
    ) {}

    public function create(Loan $loan): Response
    {
        Gate::authorize('recordRepayment', [PendingEntry::class, $loan]);

        return Inertia::render('Loans/Repay', [
            'loan' => $this->repayments->summary($loan),
            'paidFrom' => $this->repayments->paidFromOptions(),
            'previewUrl' => "/agent/loans/{$loan->uuid}/repayments/preview",
            'postUrl' => "/agent/loans/{$loan->uuid}/repayments",
            'backUrl' => "/agent/farmers/{$loan->farmerProfile->uuid}",
            'today' => now()->toDateString(),
        ]);
    }

    public function preview(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('recordRepayment', [PendingEntry::class, $loan]);

        return $this->previewFor($request, $loan);
    }

    public function store(StoreRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        Gate::authorize('recordRepayment', [PendingEntry::class, $loan]);

        try {
            $this->entries->recordRepayment($loan, $request->user(), $request->repayment());
        } catch (PostingFailed $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return redirect("/agent/farmers/{$loan->farmerProfile->uuid}");
    }
}
