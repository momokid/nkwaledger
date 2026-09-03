<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\RequestReversalRequest;
use App\Models\FarmerProfile;
use App\Models\ReversalRequest;
use App\Models\Transaction;
use App\Services\Ledger\ReversalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ReversalController extends Controller
{
    public function __construct(private readonly ReversalService $reversals) {}

    public function store(
        RequestReversalRequest $request,
        FarmerProfile|Transaction $farmerOrTransaction,
        ?Transaction $transaction = null,
    ): RedirectResponse {
        [$farmer, $record] = $this->resolve($request, $farmerOrTransaction, $transaction);

        abort_if($record->farmer_profile_id !== $farmer->id, 404);

        try {
            $this->reversals->request($record, $request->user(), $request->input('reason'));
        } catch (PostingFailed $failure) {
            throw ValidationException::withMessages(['reason' => $failure->getMessage()]);
        }

        return back()->with('success', 'Asked. Somebody will check it and let you know.');
    }

    public function approve(Request $request, ReversalRequest $reversal): RedirectResponse
    {
        try {
            $this->reversals->approve($reversal, $request->user());
        } catch (PostingFailed $failure) {
            return back()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Done. The record is cancelled and a correction is in the books.');
    }

    public function reject(Request $request, ReversalRequest $reversal): RedirectResponse
    {
        $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        try {
            $this->reversals->reject($reversal, $request->user(), $request->input('reason'));
        } catch (PostingFailed $failure) {
            return back()->with('error', $failure->getMessage());
        }

        return back()->with('success', 'Noted. The original record stays as it is.');
    }

    private function resolve(
        Request $request,
        FarmerProfile|Transaction $first,
        ?Transaction $second,
    ): array {
        if ($first instanceof FarmerProfile) {
            $user = $request->user();

            abort_if(! $user->hasRole('admin') && $first->assigned_agent_id !== $user->id, 404);

            return [$first, $second];
        }

        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return [$own, $first];
    }
}
