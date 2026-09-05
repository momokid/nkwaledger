<?php

namespace App\Http\Controllers\Transactions;

use App\Exceptions\Ledger\PostingFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\Transactions\RecordTransactionRequest;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\LedgerAccount;
use App\Models\TransactionTemplate;
use App\Services\Ledger\PostingRequest;
use App\Services\Ledger\PostingService;
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
                ]),
                'opening_balance' => $statement->openingBalanceMinor,
                'closing_balance' => $statement->closingBalanceMinor,
                'total_in' => $statement->totalInMinor,
                'total_out' => $statement->totalOutMinor,
                'cancelled' => $statement->cancelledMinor,
                'provisional_held_back' => $statement->provisionalHeldBackMinor,
                'total' => $statement->total,
                'page' => $statement->page,
                'last_page' => $statement->lastPage,
            ],
            'filters' => ['from' => $from, 'to' => $to, 'account' => $accountId],
            'accounts' => LedgerAccount::settlement()->orderBy('name')->get(['id', 'name']),
            ...$this->frame($request),
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
            'settlementAccounts' => LedgerAccount::settlement()
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

    public function store(RecordTransactionRequest $request, ?FarmerProfile $farmer = null): RedirectResponse
    {
        $data = $request->validated();

        try {
            $transaction = $this->posting->post(new PostingRequest(
                farmerProfileId: $request->farmer()->id,
                transactionTemplateId: (int) $data['transaction_template_id'],
                amount: $data['amount'],
                settlementAccountId: isset($data['settlement_account_id'])
                    ? (int) $data['settlement_account_id']
                    : null,
                transactionDate: $data['transaction_date'],
                farmUnitId: isset($data['farm_unit_id']) ? (int) $data['farm_unit_id'] : null,
                narration: $data['narration'] ?? null,
                channel: 'web',
                recordedBy: $request->user()->id,
                quantityLost: $data['quantity_lost'] ?? null,
                quantitySold: $data['quantity_sold'] ?? null,
            ));
        } catch (PostingFailed $failure) {
            return back()->withInput()->with('error', $failure->getMessage());
        }

        return back()
            ->with('success', 'Saved. Your record is in your book.')
            ->with('reference', $transaction->reference);
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
            ->get(['id', 'name', 'transaction_type', 'settlement_side', 'requires_farm_unit', 'is_produce_sale']);
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
