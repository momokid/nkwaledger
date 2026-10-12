<?php

namespace App\Http\Controllers\Loans\Concerns;

use App\Exceptions\Ledger\PostingFailed;
use App\Models\Loan;
use App\Services\Loans\LoanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// the split the server would use for an amount, shown before anything is saved. Needs $this->repayments
trait PreviewsRepayment
{
    protected function previewFor(Request $request, Loan $loan): JsonResponse
    {
        $request->validate(['amount' => ['required', 'string', 'regex:/^\d+(\.\d{1,2})?$/']], [
            'amount.required' => LoanService::AMOUNT_REFUSAL,
            'amount.regex' => LoanService::AMOUNT_REFUSAL,
        ]);

        try {
            return response()->json($this->repayments->preview($loan, (string) $request->input('amount')));
        } catch (PostingFailed $failure) {
            return response()->json(['message' => $failure->getMessage()], 422);
        }
    }
}
