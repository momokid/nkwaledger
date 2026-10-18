<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Exceptions\Loans\EntryRefused;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Models\PendingEntry;
use App\Services\Loans\EntryCodeService;
use App\Services\Loans\LoanService;
use App\Services\Loans\PendingEntryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use App\Support\Wording;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// the farmer's current agent records loans for the farmer, and sees them through to acceptance
class AgentLoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loans,
        private readonly PendingEntryService $entries,
        private readonly EntryCodeService $codes,
    ) {}

    public function newLoan(): Response
    {
        return Inertia::render('Agent/Loans/New');
    }

    // a POST, so what is typed never lands in an address. Only the agent's own farmers, never a row id
    public function searchFarmers(Request $request): JsonResponse
    {
        $check = Validator::make($request->only('q'), ['q' => ['required', 'string', 'min:2', 'max:100']]);

        if ($check->fails()) {
            return response()->json(['errors' => $check->errors()], 422);
        }

        $needle = '%' . mb_strtolower($check->validated()['q']) . '%';

        return response()->json(FarmerProfile::query()
            ->where('assigned_agent_id', $request->user()->id)
            ->where(fn(Builder $outer) => $outer
                ->whereHas('user', fn(Builder $inner) => $inner
                    ->whereRaw('LOWER(surname) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(first_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(phone) LIKE ?', [$needle]))
                ->orWhereHas('community', fn(Builder $inner) => $inner->whereRaw('LOWER(name) LIKE ?', [$needle])))
            ->with(['user:id,surname,first_name', 'community:id,name'])
            ->limit(20)
            ->get()
            ->map(fn(FarmerProfile $farmer) => [
                'id' => $farmer->uuid,
                'name' => trim("{$farmer->user?->surname} {$farmer->user?->first_name}"),
                'community' => $farmer->community?->name,
            ])
            ->values());
    }

    public function create(FarmerProfile $farmer): Response
    {
        Gate::authorize('record', [PendingEntry::class, $farmer]);

        return $this->form($farmer, Loan::KIND_MONEY);
    }

    public function createExisting(FarmerProfile $farmer): Response
    {
        Gate::authorize('record', [PendingEntry::class, $farmer]);

        return $this->form($farmer, Loan::KIND_EXISTING);
    }

    public function store(StoreLoanRequest $request, FarmerProfile $farmer): RedirectResponse
    {
        Gate::authorize('record', [PendingEntry::class, $farmer]);

        try {
            $this->entries->recordLoan($farmer, $request->user(), $request->validated());
        } catch (PostingFailed $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return redirect("/agent/farmers/{$farmer->uuid}");
    }

    public function sendCode(Request $request, PendingEntry $entry): RedirectResponse
    {
        Gate::authorize('manage', $entry);

        try {
            $this->codes->send($entry, $request->user());
        } catch (EntryRefused|PostingFailed $refusal) {
            return back()->with('error', $refusal->getMessage());
        }

        return back()->with('success', 'Code sent to the farmer.');
    }

    public function enterCode(Request $request, PendingEntry $entry): RedirectResponse
    {
        Gate::authorize('manage', $entry);

        $code = (string) $request->input('code', '');

        try {
            $result = $this->codes->enter($entry, $request->user(), $code);
        } catch (EntryRefused $refusal) {
            return back()->withErrors(['code' => $refusal->getMessage()]);
        } catch (PostingFailed $refusal) {
            return back()->with('error', $refusal->getMessage());
        }

        if ($result === null) {
            return redirect("/agent/farmers/{$entry->farmerProfile->uuid}")->with('error', Wording::get('repayment.stale'));
        }

        return redirect("/agent/farmers/{$entry->farmerProfile->uuid}")->with('success', $entry->isRepayment() ? Wording::get('repayment.accepted') : 'Loan accepted.');
    }

    public function withdraw(Request $request, PendingEntry $entry): RedirectResponse
    {
        Gate::authorize('manage', $entry);

        try {
            $this->entries->withdraw($entry, $request->user());
        } catch (EntryRefused $refusal) {
            return back()->with('error', $refusal->getMessage());
        }

        return back()->with('success', 'Loan withdrawn.');
    }

    private function form(FarmerProfile $farmer, string $kind): Response
    {
        return Inertia::render('Loans/Create', [
            'kind' => $kind,
            'paidInto' => $this->loans->paidIntoOptions(),
            'idempotencyKey' => (string) Str::uuid(),
            'agent' => true,
            'requestFor' => $farmer->uuid,
            'postUrl' => "/agent/farmers/{$farmer->uuid}/loans",
            'backUrl' => "/agent/farmers/{$farmer->uuid}",
        ]);
    }
}
