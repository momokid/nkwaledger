<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreOtherUseRequest;
use App\Models\Loan;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;

class LoanOtherUseController extends Controller
{
    public function store(StoreOtherUseRequest $request, Loan $loan, AuditService $audit): RedirectResponse
    {
        $use = $loan->otherUses()->create([
            'amount_minor' => $request->amountMinor(),
            'note' => trim($request->validated('note')),
            'recorded_by' => $request->user()->id,
        ]);

        $audit->recordOn('loan.other_use', $loan, null, ['uuid' => $use->uuid, 'amount_minor' => $use->amount_minor], $request->user());

        return back();
    }
}
