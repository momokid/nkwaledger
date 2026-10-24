<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Loans\Concerns\PreviewsRepayment;
use App\Http\Requests\Loans\StoreRepaymentRequest;
use App\Models\Loan;
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
            'repayments' => $this->list->repaymentRows($loan),
            'rejected' => $this->list->rejectedRepayments($loan),
            'farms' => $loan->farmFigures(),
            'unaccounted' => $loan->unaccountedMinor(),
            'otherUses' => $loan->otherUses()->orderBy('id')->get()->map(fn($use) => ['uuid' => $use->uuid, 'note' => $use->note, 'amount' => $use->amount_minor])->values(),
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

    public function store(StoreRepaymentRequest $request, Loan $loan): RedirectResponse|JsonResponse
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
            return $request->expectsJson()
                ? response()->json(['message' => $failure->getMessage()], 422)
                : back()->withInput()->with('error', $failure->getMessage());
        }

        // saved in place: the page gets the new figures and a fresh key, and clears its form without reloading
        if ($request->expectsJson()) {
            $loan->refresh();

            return response()->json([
                'loan' => $this->repayments->summary($loan),
                'repayments' => $this->list->repaymentRows($loan),
                'rejected' => $this->list->rejectedRepayments($loan),
                'idempotencyKey' => (string) Str::uuid(),
            ], 201);
        }

        return redirect("/loans/{$loan->uuid}");
    }
}
