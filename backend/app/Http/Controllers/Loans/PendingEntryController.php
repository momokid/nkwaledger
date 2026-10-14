<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Http\Controllers\Controller;
use App\Models\PendingEntry;
use App\Services\Loans\PendingEntryService;
use Illuminate\Http\RedirectResponse;
use App\Support\Wording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

// the farmer answers an agent's entry in the app
class PendingEntryController extends Controller
{
    public function __construct(private readonly PendingEntryService $entries) {}

    public function accept(Request $request, PendingEntry $entry): RedirectResponse
    {
        Gate::authorize('respond', $entry);

        try {
            $result = $this->entries->accept($entry, $request->user(), PendingEntry::VIA_APP, $request->boolean('confirm_excess'));
        } catch (PostingFailed|EntryRefused $refusal) {
            return redirect('/loans')->with('error', $refusal->getMessage());
        }

        if ($result === null) {
            return redirect('/loans')->with('error', Wording::get('repayment.stale'));
        }

        return redirect('/loans')->with('success', $entry->isRepayment() ? Wording::get('repayment.accepted') : 'Loan accepted.');
    }

    public function decline(Request $request, PendingEntry $entry): RedirectResponse
    {
        Gate::authorize('respond', $entry);

        try {
            $this->entries->decline($entry, $request->user());
        } catch (EntryRefused $refusal) {
            return redirect('/loans')->with('error', $refusal->getMessage());
        }

        return redirect('/loans')->with('success', 'You declined this loan.');
    }
}
