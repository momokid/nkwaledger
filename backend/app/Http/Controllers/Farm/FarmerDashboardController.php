<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Transaction;
use App\Services\Ledger\Reports\IncomeAndExpenditure;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FarmerDashboardController extends Controller
{
    private const FARM_PRODUCE_LIMIT = 4;

    public function __construct(
        private readonly IncomeAndExpenditureService $incomes,
    ) {}

    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        if ($farmer === null) {
            return Inertia::render('Dashboard', [
                'summary' => $this->emptySummary(),
                'farm_produce' => $this->emptyFarmProduce(),
                'breakdown' => $this->emptyBreakdown(),
                'recent_transactions' => [],
                'filters' => ['from' => $from, 'to' => $to],
            ]);
        }

        // a farmer sees every record on their own books, confirmed or not
        $report = $this->incomes->for($farmer->id, $from, $to, includeProvisional: true);

        [$previousFrom, $previousTo] = $this->previousPeriod($from, $to);
        $previousReport = $this->incomes->for($farmer->id, $previousFrom, $previousTo, includeProvisional: true);

        return Inertia::render('Dashboard', [
            'summary' => $this->summaryFrom($report, $previousReport),
            'farm_produce' => $this->farmProduceFor($farmer->id),
            'breakdown' => $this->breakdownFrom($report),
            'recent_transactions' => $this->recentTransactionsFor($farmer->id),
            'filters' => ['from' => $from, 'to' => $to],
        ]);
    }

    private function summaryFrom(IncomeAndExpenditure $report, IncomeAndExpenditure $previous): array
    {
        $income = $report->totalIncomeMinor;
        $expense = $report->totalExpenseMinor;
        $net = $income - $expense;

        $prevIncome = $previous->totalIncomeMinor;
        $prevExpense = $previous->totalExpenseMinor;
        $prevNet = $prevIncome - $prevExpense;

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'net' => $net,
            'trends' => [
                'income' => $this->trend($income, $prevIncome, higherIsGood: true),
                'expense' => $this->trend($expense, $prevExpense, higherIsGood: false),
                'net' => $this->trend($net, $prevNet, higherIsGood: true),
            ],
        ];
    }

    private function breakdownFrom(IncomeAndExpenditure $report): array
    {
        $lines = fn(array $rows) => collect($rows)->map(fn($row) => [
            'account' => $row->accountName,
            'group' => $row->groupName,
            'amount' => $row->amountMinor,
        ])->all();

        return [
            'income_rows' => $lines($report->incomeRows),
            'expense_rows' => $lines($report->expenseRows),
            'loss_rows' => $lines($report->lossRows),
        ];
    }

    private function emptyBreakdown(): array
    {
        return ['income_rows' => [], 'expense_rows' => [], 'loss_rows' => []];
    }

    private function recentTransactionsFor(int $farmerId): array
    {
        return Transaction::query()
            ->with('template:id,name')
            ->where('farmer_profile_id', $farmerId)
            ->whereIn('transaction_type', [Transaction::INCOME, Transaction::EXPENSE])
            // a cancelled record never happened, as far as the farmer's eye is concerned
            ->whereDoesntHave('reversedBy')
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get()
            ->map(fn(Transaction $transaction) => [
                'name' => $transaction->template?->name ?? 'Record',
                'date' => $transaction->transaction_date->toDateString(),
                'amount' => $transaction->amount_minor,
                'income' => $transaction->transaction_type === Transaction::INCOME,
            ])
            ->all();
    }

    // one row per farm unit, never merged across farms even when the type matches;
    // whole-number types (headcount) round for display, decimal types (area, weight) keep the fraction
    private function farmProduceFor(int $farmerId): array
    {
        $rows = FarmUnit::query()
            ->where('farm_units.farmer_profile_id', $farmerId)
            ->whereNotNull('farm_units.approved_at')
            ->join('farm_types', 'farm_types.id', '=', 'farm_units.farm_type_id')
            ->leftJoin('farm_unit_stocks', function ($join) {
                $join->on('farm_unit_stocks.farm_unit_id', '=', 'farm_units.id')
                    ->whereNotNull('farm_unit_stocks.confirmed_at')
                    ->whereNull('farm_unit_stocks.rejected_at');
            })
            ->select(
                'farm_units.id as unit_id',
                'farm_units.name as farm',
                'farm_types.name as type',
                'farm_types.quantity_is_decimal as is_decimal',
            )
            ->selectRaw('COALESCE(SUM(farm_unit_stocks.current_quantity), 0) as quantity')
            ->selectRaw('MAX(farm_unit_stocks.unit_of_measure) as unit')
            ->groupBy('farm_units.id', 'farm_units.name', 'farm_types.name', 'farm_types.quantity_is_decimal')
            // sorted by type then farm, not quantity — comparing "50 birds" against
            // "2.5 acres" as raw numbers would rank them meaninglessly
            ->orderBy('farm_types.name')
            ->orderBy('farm_units.name')
            ->get();

        $items = $rows->take(self::FARM_PRODUCE_LIMIT)->map(fn($row) => [
            'farm' => $row->farm,
            'type' => $row->type,
            'quantity' => $this->formatQuantity((float) $row->quantity, (bool) $row->is_decimal),
            'unit' => $row->unit,
        ])->all();

        return [
            'items' => $items,
            'more_count' => max(0, $rows->count() - self::FARM_PRODUCE_LIMIT),
        ];
    }

    private function formatQuantity(float $quantity, bool $isDecimal): string
    {
        return $isDecimal
            ? number_format($quantity, 2, '.', '')
            : number_format(round($quantity), 0, '.', '');
    }

    private function emptyFarmProduce(): array
    {
        return ['items' => [], 'more_count' => 0];
    }

    private function previousPeriod(string $from, string $to): array
    {
        $start = Carbon::parse($from);
        $end = Carbon::parse($to);
        $lengthInDays = $start->diffInDays($end) + 1;

        $previousTo = $start->copy()->subDay();
        $previousFrom = $previousTo->copy()->subDays($lengthInDays - 1);

        return [$previousFrom->toDateString(), $previousTo->toDateString()];
    }

    private function trend(int $current, int $previous, bool $higherIsGood): array
    {
        $direction = $current <=> $previous;

        return [
            'direction' => $direction > 0 ? 'up' : ($direction < 0 ? 'down' : 'flat'),
            'percent' => $previous === 0 ? null : (int) round(abs($current - $previous) / abs($previous) * 100),
            'good' => $higherIsGood ? $current >= $previous : $current <= $previous,
        ];
    }

    private function emptySummary(): array
    {
        $flat = ['direction' => 'flat', 'percent' => null, 'good' => true];

        return [
            'total_income' => 0,
            'total_expense' => 0,
            'net' => 0,
            'trends' => ['income' => $flat, 'expense' => $flat, 'net' => $flat],
        ];
    }

    private function resolveFarmer(Request $request): ?FarmerProfile
    {
        return FarmerProfile::query()->where('user_id', $request->user()->id)->first();
    }
}
