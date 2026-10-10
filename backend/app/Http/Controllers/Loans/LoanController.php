<?php

namespace App\Http\Controllers\Loans;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Loans\StoreLoanRequest;
use App\Models\FarmerProfile;
use App\Models\Loan;
use App\Services\Loans\LoanService;
use App\Services\RecordLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

// the farmer's own loans: the list, the two forms and saving. Nobody is named in the address
class LoanController extends Controller
{
    public function __construct(
        private readonly LoanService $loans,
        private readonly RecordLock $lock,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Loan::class);

        $farmer = $this->farmer($request);

        return Inertia::render('Loans/Index', [
            'loans' => Loan::query()
                ->where('farmer_profile_id', $farmer->id)
                ->orderByDesc('id')
                ->get()
                ->map(fn(Loan $loan) => [
                    'uuid' => $loan->uuid,
                    'lender' => $loan->lender_name,
                    'amount' => $loan->principal_minor,
                    'balance' => $loan->balanceMinor(),
                    'status' => $loan->displayStatus(),
                ])
                ->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Loan::class);

        return $this->form(Loan::KIND_MONEY);
    }

    public function createExisting(Request $request): Response
    {
        Gate::authorize('create', Loan::class);

        return $this->form(Loan::KIND_EXISTING);
    }

    public function store(StoreLoanRequest $request): RedirectResponse
    {
        Gate::authorize('create', Loan::class);

        $farmer = $this->farmer($request);
        $data = $request->validated();

        try {
            $this->lock->around(
                $request->user()->id,
                $data['idempotency_key'] ?? null,
                fn() => $this->loans->record($farmer, $request->user(), $data),
            );
        } catch (PostingFailed $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return redirect('/loans');
    }

    private function form(string $kind): Response
    {
        return Inertia::render('Loans/Create', [
            'kind' => $kind,
            'lenderTypes' => Loan::LENDER_TYPES,
            'paidInto' => $this->loans->paidIntoOptions(),
            'idempotencyKey' => (string) Str::uuid(),
        ]);
    }

    private function farmer(Request $request): FarmerProfile
    {
        return FarmerProfile::query()->where('user_id', $request->user()->id)->firstOrFail();
    }
}
