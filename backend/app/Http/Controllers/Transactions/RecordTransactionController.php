<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\RecordTransactionRequest;
use App\Http\Requests\Transactions\SettleTransactionRequest;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\PostingRequest;
use App\Services\AccessControlService;
use App\Services\Ledger\PostingService;
use App\Services\RecordLock;
use App\Services\SyncSubmissionService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use App\Services\Ledger\Reports\AccountStatementService;
use Illuminate\Support\Carbon;
use App\Models\Transaction;

class RecordTransactionController extends Controller
{
    public function __construct(
        private readonly PostingService $posting,
        private readonly AccountStatementService $statements,
        private readonly CreditSettlementService $creditSettlements,
        private readonly RecordLock $lock,
        private readonly AccessControlService $access,
        private readonly SyncSubmissionService $sync,
    ) {}

    public function index(Request $request, ?FarmerProfile $farmer = null): Response
    {
        $farmer = $this->resolveFarmer($request, $farmer);

        // this month unless they ask for something else
        $from = $request->query('from', Carbon::now()->startOfMonth()->toDateString());
        $to = $request->query('to', Carbon::now()->endOfMonth()->toDateString());
        $accountId = $request->query('account') !== null ? (int) $request->query('account') : null;

        $statement = $this->statements->for(
            farmerProfileId: $farmer->id,
            from: $from,
            to: $to,
            includeProvisional: true,
            accountId: $accountId,
            page: (int) $request->query('page', 1),
            perPage: (int) $request->query('per_page', 25),
        );

        return Inertia::render('Transactions/Index', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
            ],
            'statement' => [
                'rows' => collect($statement->rows)->map(fn($row) => [
                    'uuid' => $row->uuid,
                    'reference' => $row->reference,
                    'date' => $row->transactionDate,
                    'description' => $row->description,
                    'type' => $row->transactionType,
                    'money_in' => $row->moneyInMinor,
                    'money_out' => $row->moneyOutMinor,
                    'balance' => $row->balanceMinor,
                    'is_provisional' => $row->isProvisional,
                    'cancel_state' => $row->cancelState,
                    'account' => $row->accountName,
                    'value_lost' => $row->valueLostMinor,
                    'money_class' => $row->moneyClass?->value,
                ]),
                'opening_balance' => $statement->openingBalanceMinor,
                'closing_balance' => $statement->closingBalanceMinor,
                'total_in' => $statement->totalInMinor,
                'total_out' => $statement->totalOutMinor,
                'total_assets' => $statement->totalAssetsMinor,
                'total_expenditure' => $statement->totalExpenditureMinor,
                'total_income' => $statement->totalIncomeMinor,
                'total_liability' => $statement->totalLiabilityMinor,
                'cancelled_in' => $statement->cancelledInMinor,
                'cancelled_out' => $statement->cancelledOutMinor,
                'provisional_held_back' => $statement->provisionalHeldBackMinor,
                'total' => $statement->total,
                'page' => $statement->page,
                'last_page' => $statement->lastPage,
            ],
            'filters' => ['from' => $from, 'to' => $to, 'account' => $accountId],
            'flagged' => $this->sync->flaggedFor($request->user(), $farmer),
            'accounts' => LedgerAccount::settlement()
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->orderBy('name')
                ->get(['id', 'name']),
            // unfiltered by date range - a credit sale from three months ago is still
            // owed today, so scoping it to "this month" would just hide it
            'creditRows' => $this->creditRows($farmer),
            // the same permission the settle routes ask for, so the button never promises a 403
            'canSettle' => $this->access->can($request->user(), 'transactions.create'),
            'creditSettlementAccounts' => LedgerAccount::settlement()
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->orderBy('name')
                ->get(['id', 'name']),
            ...$this->frame($request),
        ]);
    }

    // every credit sale/purchase, with what is still owed on each - fully settled
    // ones stay listed (marked paid) rather than vanishing, so the tab still
    // reads as a record of what was ever put on credit, not just a to-do list
    private function creditRows(FarmerProfile $farmer): Collection
    {
        return Transaction::query()
            ->notCancelled()
            ->where('farmer_profile_id', $farmer->id)
            ->where('is_credit', true)
            ->with('template')
            ->orderByDesc('transaction_date')
            ->get()
            ->map(fn(Transaction $transaction) => [
                'uuid' => $transaction->uuid,
                'reference' => $transaction->reference,
                'date' => $transaction->transaction_date->toDateString(),
                'description' => $transaction->template?->name,
                'narration' => $transaction->narration,
                'amount' => $transaction->amount_minor,
                'outstanding' => $this->creditSettlements->outstandingAmount($transaction),
            ]);
    }

    public function create(Request $request, ?FarmerProfile $farmer = null): Response
    {
        $farmer = $this->resolveFarmer($request, $farmer);

        return Inertia::render('Transactions/Create', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
            ],
            'templates' => $this->templates($farmer),
            // Receivable/Payable are settlement accounts too (so a credit sale/purchase can be
            // posted against them), but a farmer never picks them directly - "Credit (not paid
            // yet)" is a separate, synthetic choice the frontend adds for allows_credit templates
            'settlementAccounts' => LedgerAccount::settlement()
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->orderBy('name')
                ->get(['id', 'name']),
            'farmUnits' => $farmer->farmUnits()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'approved_at'])
                ->map(fn(FarmUnit $unit) => [
                    'id' => $unit->id,
                    'name' => $unit->name,
                    // anything on an unapproved unit counts toward nothing yet
                    'is_approved' => $unit->approved_at !== null,
                ]),
            ...$this->frame($request),
        ]);
    }

    public function store(RecordTransactionRequest $request, ?FarmerProfile $farmer = null): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        try {
            $template = TransactionTemplate::findOrFail((int) $data['transaction_template_id']);

            $settlementAccountId = ($data['is_credit'] ?? false)
                ? $template->creditSettlementAccountId()
                : (isset($data['settlement_account_id']) ? (int) $data['settlement_account_id'] : null);

            $posting = new PostingRequest(
                farmerProfileId: $request->farmer()->id,
                transactionTemplateId: $template->id,
                amount: $data['amount'],
                settlementAccountId: $settlementAccountId,
                transactionDate: $data['transaction_date'],
                farmUnitId: isset($data['farm_unit_id']) ? (int) $data['farm_unit_id'] : null,
                narration: $data['narration'] ?? null,
                channel: 'web',
                recordedBy: $request->user()->id,
                quantityLost: $data['quantity_lost'] ?? null,
                quantitySold: $data['quantity_sold'] ?? null,
                quantityPurchased: $data['quantity_purchased'] ?? null,
                idempotencyKey: $data['idempotency_key'] ?? null,
            );

            $transaction = $this->lock->around(
                $request->user()->id,
                $posting->idempotencyKey,
                fn() => $this->syncTwin($request->user(), $posting) ?? $this->posting->post($posting),
            );
        } catch (PostingFailed $failure) {
            // the offline sync engine has no page to redirect back to, so it needs a real HTTP error
            if ($request->wantsJson()) {
                return response()->json(['message' => $failure->getMessage()], $failure->isSystem() ? 503 : 422);
            }

            return back()->withInput()->with('error', $failure->getMessage());
        }

        if ($request->wantsJson()) {
            return response()->json(['reference' => $transaction->reference]);
        }

        return back()
            ->with('success', 'Saved. Your record is in your book.')
            ->with('reference', $transaction->reference);
    }

    // the same record may already have arrived through sync, which keys it differently
    private function syncTwin(User $user, PostingRequest $posting): ?Transaction
    {
        if ($posting->idempotencyKey === null) {
            return null;
        }

        $twin = Transaction::where('idempotency_key', "sync.{$user->id}.{$posting->idempotencyKey}")->first();

        if ($twin === null) {
            return null;
        }

        if ((int) $twin->farmer_profile_id !== $posting->farmerProfileId
            || ! Transaction::sameDetails($twin->transaction_template_id, Money::toDecimal($twin->amount_minor), $posting->transactionTemplateId, $posting->amount)) {
            throw PostingFailed::because(Transaction::KEY_REUSED);
        }

        return $twin;
    }

    public function settle(
        SettleTransactionRequest $request,
        Transaction $transaction,
        ?FarmerProfile $farmer = null,
    ): RedirectResponse {
        $farmer = $this->resolveFarmer($request, $farmer);

        abort_if($transaction->farmer_profile_id !== $farmer->id, 404);

        $data = $request->validated();

        try {
            $this->creditSettlements->settle(
                original: $transaction,
                amountMinor: Money::toMinor($data['amount']),
                settlementAccountId: (int) $data['settlement_account_id'],
                transactionDate: now()->toDateString(),
                recordedBy: $request->user()->id,
            );
        } catch (PostingFailed $failure) {
            throw ValidationException::withMessages(['amount' => $failure->getMessage()]);
        }

        return back()->with('success', 'Payment recorded.');
    }

    // the farmer's own page names nobody, the agent's page names the farmer
    private function resolveFarmer(Request $request, ?FarmerProfile $farmer): FarmerProfile
    {
        $user = $request->user();

        if ($farmer === null) {
            $own = FarmerProfile::query()->with('user')->where('user_id', $user->id)->first();

            abort_if($own === null, 403);

            return $own;
        }

        // an agent works their own book, an admin sees the whole platform
        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);

        return $farmer->load('user');
    }

    private function templates(FarmerProfile $farmer): Collection
    {
        return TransactionTemplate::query()
            ->where('is_active', true)
            // a farmer never cancels their own record
            ->where('transaction_type', '!=', Transaction::ADJUSTMENT)
            ->where(fn($query) => $query
                ->whereIn('farm_type_category_id', $farmer->farmTypes()->pluck('category_id'))
                // some things are true on every farm, so they belong to no category
                ->orWhereNull('farm_type_category_id'))
            ->orderBy('name')
            ->get(['id', 'name', 'transaction_type', 'settlement_side', 'requires_farm_unit', 'is_produce_sale', 'is_stock_purchase', 'allows_credit']);
    }

    // the acting user's real role decides the layout, never which URL/route name
    // happened to be hit (Sept 2026 privilege-escalation fix). The frontend only
    // knows "farmer" | "agent" for this page (an admin viewing via
    // /admin/farmers/{farmer}/records already fell into the "farmer" bucket before
    // this fix too - unchanged, since that is a separate, non-security pre-existing
    // gap in this page, not something this fix should guess a new behaviour for)
    private function frame(Request $request): array
    {
        $group = $request->user()?->hasRole('agent') ? 'agent' : 'farmer';

        return [
            'layout' => $group,
            'basePath' => $group === 'agent' ? '/agent/records' : '/my-records',
        ];
    }
}
