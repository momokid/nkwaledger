<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Loans\Concerns\PreviewsRepayment;
use App\Http\Requests\Loans\StoreRepaymentRequest;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Services\Loans\LoanListService;
use App\Services\Loans\RepaymentService;
use App\Services\RecordLock;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// the farmer's own loan page and the repayments made on it
class RepaymentController extends Controller
{
    use PreviewsRepayment;

    public function __construct(
        private readonly RepaymentService $repayments,
        private readonly RecordLock $lock,
        private readonly LoanListService $list,
    ) {}

    public function show(Loan $loan): Response
    {
        Gate::authorize('repay', $loan);

        return Inertia::render('Loans/Show', [
            'loan' => $this->repayments->summary($loan),
            'repayments' => $loan->repayments()
                ->with(['principalTransaction.reversedBy', 'interestTransaction.reversedBy'])
                ->orderByDesc('repaid_on')
                ->orderByDesc('id')
                ->get()
                ->map(fn(LoanRepayment $repayment) => [
                    'uuid' => $repayment->uuid,
                    'date' => $repayment->repaid_on->toDateString(),
                    'amount' => $repayment->amount_minor,
                    'principal' => $repayment->principal_minor,
                    'interest' => $repayment->interest_minor,
                    'cancelled' => $repayment->principalTransaction?->reversedBy !== null || $repayment->interestTransaction?->reversedBy !== null,
                ])
                ->values(),
            'rejected' => $this->list->rejectedRepayments($loan),
            'paidFrom' => $this->repayments->paidFromOptions(),
            'idempotencyKey' => (string) Str::uuid(),
            'previewUrl' => "/loans/{$loan->uuid}/repayments/preview",
            'postUrl' => "/loans/{$loan->uuid}/repayments",
            'today' => now()->toDateString(),
        ]);
    }

    public function preview(Request $request, Loan $loan): JsonResponse
    {
        Gate::authorize('repay', $loan);

        return $this->previewFor($request, $loan);
    }

    public function store(StoreRepaymentRequest $request, Loan $loan): RedirectResponse
    {
        Gate::authorize('repay', $loan);

        $data = $request->repayment();

        try {
            $this->lock->around(
                $request->user()->id,
                $data['idempotency_key'],
                fn() => $this->repayments->record($loan, $request->user(), $data),
            );
        } catch (PostingFailed $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return redirect("/loans/{$loan->uuid}");
    }
}
