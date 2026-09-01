<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Ledger\Reports\ReportHeader;
use App\Services\Ledger\Reports\TrialBalanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    private const FARMER_REPORTS = ['statement', 'income'];

    private const STAFF_REPORTS = ['statement', 'income', 'trial-balance'];

    public function __construct(
        private readonly AccountStatementService $statements,
        private readonly IncomeAndExpenditureService $incomes,
        private readonly TrialBalanceService $trialBalances,
    ) {}

    public function index(Request $request, ?FarmerProfile $farmer = null): Response
    {
        $ownBooks = $farmer === null;

        $farmer = $this->resolveFarmer($request, $farmer);

        $available = $ownBooks ? self::FARMER_REPORTS : self::STAFF_REPORTS;

        $kind = $request->query('kind', 'statement');

        if (! in_array($kind, $available, true)) {
            $kind = 'statement';
        }

        $from = $request->query('from', Carbon::now()->startOfYear()->toDateString());
        $to = $request->query('to', Carbon::now()->endOfYear()->toDateString());

        // a farmer sees all of their own records, a bank sees only what is confirmed
        $includeProvisional = $ownBooks || $request->query('provisional') === '1';

        return Inertia::render('Reports/Index', [
            'farmer' => [
                'id' => $farmer->uuid,
                'name' => "{$farmer->user?->surname} {$farmer->user?->first_name}",
            ],
            'available' => $available,
            'kind' => $kind,
            'report' => $this->build($kind, $farmer, $from, $to, $includeProvisional),
            'filters' => [
                'from' => $from,
                'to' => $to,
                'provisional' => $includeProvisional,
            ],
            'canChooseProvisional' => ! $ownBooks,
            ...$this->frame($request),
        ]);
    }

    private function build(
        string $kind,
        FarmerProfile $farmer,
        string $from,
        string $to,
        bool $includeProvisional,
    ): array {
        if ($kind === 'income') {
            return $this->income($farmer, $from, $to, $includeProvisional);
        }

        if ($kind === 'trial-balance') {
            return $this->trialBalance($farmer, $from, $to, $includeProvisional);
        }

        return $this->statement($farmer, $from, $to, $includeProvisional);
    }

    private function statement(FarmerProfile $farmer, string $from, string $to, bool $includeProvisional): array
    {
        $report = $this->statements->for(
            farmerProfileId: $farmer->id,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
            perPage: 500,
        );

        return [
            'header' => $this->header($report->header),
            'rows' => collect($report->rows)->map(fn($row) => [
                'reference' => $row->reference,
                'date' => $row->transactionDate,
                'description' => $row->description,
                'account' => $row->accountName,
                'money_in' => $row->moneyInMinor,
                'money_out' => $row->moneyOutMinor,
                'balance' => $row->balanceMinor,
                'is_provisional' => $row->isProvisional,
                'cancel_state' => $row->cancelState,
                'value_lost' => $row->valueLostMinor,
            ]),
            'opening_balance' => $report->openingBalanceMinor,
            'closing_balance' => $report->closingBalanceMinor,
            'total_in' => $report->totalInMinor,
            'total_out' => $report->totalOutMinor,
            'cancelled' => $report->cancelledMinor,
            'provisional_held_back' => $report->provisionalHeldBackMinor,
        ];
    }

    private function income(FarmerProfile $farmer, string $from, string $to, bool $includeProvisional): array
    {
        $report = $this->incomes->for(
            farmerProfileId: $farmer->id,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
        );

        $lines = fn(array $rows) => collect($rows)->map(fn($row) => [
            'account' => $row->accountName,
            'group' => $row->groupName,
            'amount' => $row->amountMinor,
        ]);

        return [
            'header' => $this->header($report->header),
            'income_rows' => $lines($report->incomeRows),
            'expense_rows' => $lines($report->expenseRows),
            'loss_rows' => $lines($report->lossRows),
            'total_income' => $report->totalIncomeMinor,
            'total_expense' => $report->totalExpenseMinor,
            'total_loss' => $report->totalLossMinor,
            'net' => $report->netMinor,
            'provisional_held_back' => $report->provisionalHeldBackMinor,
        ];
    }

    private function trialBalance(FarmerProfile $farmer, string $from, string $to, bool $includeProvisional): array
    {
        $report = $this->trialBalances->for(
            farmerProfileId: $farmer->id,
            from: $from,
            to: $to,
            includeProvisional: $includeProvisional,
        );

        return [
            'header' => $this->header($report->header),
            'rows' => collect($report->rows)->map(fn($row) => [
                'account' => $row->accountName,
                'code' => $row->accountCode,
                'class' => $row->class,
                'debit' => $row->debitMinor,
                'credit' => $row->creditMinor,
                'balance' => $row->balanceMinor,
            ]),
            'total_debit' => $report->totalDebitMinor,
            'total_credit' => $report->totalCreditMinor,
            'is_balanced' => $report->isBalanced(),
            'provisional_held_back' => $report->provisionalHeldBackMinor,
        ];
    }

    private function header(ReportHeader $header): array
    {
        return [
            'title' => $header->title,
            'farmer_name' => $header->farmerName,
            'farmer_phone' => $header->farmerPhone,
            'farmer_reference' => $header->farmerReference,
            'from' => $header->from,
            'to' => $header->to,
            'include_provisional' => $header->includeProvisional,
            'prepared_by' => $header->preparedBy,
            'generated_at' => $header->generatedAt->toIso8601String(),
            'verification_code' => $header->verificationCode,
            'notice' => $header->notice,
        ];
    }

    private function resolveFarmer(Request $request, ?FarmerProfile $farmer): FarmerProfile
    {
        $user = $request->user();

        if ($farmer === null) {
            $own = FarmerProfile::query()->with('user')->where('user_id', $user->id)->first();

            abort_if($own === null, 403);

            return $own;
        }

        abort_if(! $user->hasRole('admin') && $farmer->assigned_agent_id !== $user->id, 404);

        return $farmer->load('user');
    }

    private function frame(Request $request): array
    {
        $name = $request->route()?->getName() ?? '';

        $group = match (true) {
            str_starts_with($name, 'agent.') => 'agent',
            str_starts_with($name, 'admin.') => 'admin',
            default => 'farmer',
        };

        return [
            'layout' => $group,
            'basePath' => $group === 'farmer' ? '/my-reports' : "/{$group}",
        ];
    }
}
