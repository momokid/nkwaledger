<?php

namespace App\Http\Controllers\Loans;

use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreOtherUseRequest;
use App\Models\Loan;
use App\Services\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanOtherUseController extends Controller
{
    public function store(StoreOtherUseRequest $request, Loan $loan, AuditService $audit): RedirectResponse
    {
        // the loan row is locked while the amount left is checked and the use saved, so two quick taps cannot both fit
        $use = DB::transaction(function () use ($request, $loan) {
            $locked = Loan::query()->whereKey($loan->id)->lockForUpdate()->firstOrFail();

            if ($request->amountMinor() > $locked->unaccountedMinor()) {
                throw ValidationException::withMessages(['amount' => 'That is more than what is left to place.']);
            }

            return $locked->otherUses()->create([
                'amount_minor' => $request->amountMinor(),
                'note' => trim($request->validated('note')),
                'recorded_by' => $request->user()->id,
            ]);
        });

        $audit->recordOn('loan.other_use', $loan, null, ['uuid' => $use->uuid, 'amount_minor' => $use->amount_minor], $request->user());

        return back();
    }
}
