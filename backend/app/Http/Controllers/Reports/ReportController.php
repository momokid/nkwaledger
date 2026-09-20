<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Services\Ledger\Reports\AccountStatementService;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use App\Services\Ledger\Reports\ReportHeader;
use App\Services\Ledger\Reports\TrialBalanceService;
use App\Support\Money;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Contracts\View\View as ViewResponse;

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
                'money_class' => $row->moneyClass?->value,
            ]),
            'opening_balance' => $report->openingBalanceMinor,
            'closing_balance' => $report->closingBalanceMinor,
            'total_in' => $report->totalInMinor,
            'total_out' => $report->totalOutMinor,
            'total_assets' => $report->totalAssetsMinor,
            'total_expenditure' => $report->totalExpenditureMinor,
            'total_income' => $report->totalIncomeMinor,
            'total_liability' => $report->totalLiabilityMinor,
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
            // net profit above stays accrual-based; these two are the cash/MoMo
            // portion actually received or paid so far, shown alongside it
            'cash_collected' => $report->cashCollectedMinor,
            'cash_paid_out' => $report->cashPaidOutMinor,
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

    public function print(Request $request, ?FarmerProfile $farmer = null): ViewResponse
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

        $includeProvisional = $ownBooks || $request->query('provisional') === '1';

        return view('reports.print', [
            'kind' => $kind,
            'report' => $this->build($kind, $farmer, $from, $to, $includeProvisional),
        ]);
    }

    // the statement only - same layout as reports.print, just handed to dompdf
    // instead of the browser's own print dialog
    public function pdf(Request $request, ?FarmerProfile $farmer = null): HttpResponse
    {
        [$farmer, $report] = $this->statementFor($request, $farmer);

        // uncompressed, so the same file that goes to the farmer is also the one
        // a test (or a support agent) can grep for the numbers it should contain
        $binary = Pdf::loadView('reports.print', ['kind' => 'statement', 'report' => $report])
            ->output(['compress' => 0]);

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $this->filenameFor($farmer, $report, 'pdf') . '"',
        ]);
    }

    public function csv(Request $request, ?FarmerProfile $farmer = null): HttpResponse
    {
        [$farmer, $report] = $this->statementFor($request, $farmer);

        $handle = fopen('php://temp', 'w+');

        fputcsv($handle, ['Date', 'Reference', 'Description', 'Money In (GHS)', 'Money Out (GHS)', 'Balance (GHS)']);
        fputcsv($handle, ['', '', 'Brought forward', '', '', Money::toDecimal($report['opening_balance'])]);

        foreach ($report['rows'] as $row) {
            fputcsv($handle, [
                $row['date'],
                $row['reference'],
                $row['description'],
                $row['money_in'] > 0 ? Money::toDecimal($row['money_in']) : '',
                $row['money_out'] > 0 ? Money::toDecimal($row['money_out']) : '',
                Money::toDecimal($row['balance']),
            ]);
        }

        fputcsv($handle, [
            '', '', 'Totals',
            Money::toDecimal($report['total_in']),
            Money::toDecimal($report['total_out']),
            Money::toDecimal($report['closing_balance']),
        ]);

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $this->filenameFor($farmer, $report, 'csv') . '"',
        ]);
    }

    /** @return array{0: FarmerProfile, 1: array} */
    private function statementFor(Request $request, ?FarmerProfile $farmer): array
    {
        $ownBooks = $farmer === null;

        $farmer = $this->resolveFarmer($request, $farmer);

        $from = $request->query('from', Carbon::now()->startOfYear()->toDateString());
        $to = $request->query('to', Carbon::now()->endOfYear()->toDateString());

        $includeProvisional = $ownBooks || $request->query('provisional') === '1';

        return [$farmer, $this->statement($farmer, $from, $to, $includeProvisional)];
    }

    private function filenameFor(FarmerProfile $farmer, array $report, string $extension): string
    {
        $name = Str::slug(trim("{$farmer->user?->surname} {$farmer->user?->first_name}")) ?: 'farmer';

        return "statement-{$name}-{$report['header']['from']}-to-{$report['header']['to']}.{$extension}";
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
