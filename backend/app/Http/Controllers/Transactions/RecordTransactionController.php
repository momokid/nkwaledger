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
use App\Services\Ledger\CreditSettlementService;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
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
                'cancelled' => $statement->cancelledMinor,
                'provisional_held_back' => $statement->provisionalHeldBackMinor,
                'total' => $statement->total,
                'page' => $statement->page,
                'last_page' => $statement->lastPage,
            ],
            'filters' => ['from' => $from, 'to' => $to, 'account' => $accountId],
            'accounts' => LedgerAccount::settlement()
                ->whereNotIn('name', ['Accounts Receivable', 'Accounts Payable'])
                ->orderBy('name')
                ->get(['id', 'name']),
            // unfiltered by date range - a credit sale from three months ago is still
            // owed today, so scoping it to "this month" would just hide it
            'creditRows' => $this->creditRows($farmer),
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
                ? $this->creditSettlementAccountFor($template)
                : (isset($data['settlement_account_id']) ? (int) $data['settlement_account_id'] : null);

            $transaction = $this->posting->post(new PostingRequest(
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
            ));
        } catch (PostingFailed $failure) {
            // the offline sync engine has no page to redirect back to, so it needs a real HTTP error
            if ($request->wantsJson()) {
                return response()->json(['message' => $failure->getMessage()], 422);
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

    // the farmer never sees or chooses "Receivable"/"Payable" - which one applies
    // follows straight from whether money is coming in or going out
    private function creditSettlementAccountFor(TransactionTemplate $template): int
    {
        $name = match ($template->transaction_type) {
            Transaction::INCOME => 'Accounts Receivable',
            Transaction::EXPENSE => 'Accounts Payable',
            default => throw PostingFailed::because('That kind of record cannot be put on credit.'),
        };

        $accountId = LedgerAccount::where('name', $name)->value('id');

        if ($accountId === null) {
            throw PostingFailed::because('Credit is not set up yet.');
        }

        return $accountId;
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

    private function frame(Request $request): array
    {
        $name = $request->route()?->getName() ?? '';
        $group = str_starts_with($name, 'agent.') ? 'agent' : 'farmer';

        return [
            'layout' => $group,
            'basePath' => $group === 'agent' ? '/agent/records' : '/my-records',
        ];
    }
}
