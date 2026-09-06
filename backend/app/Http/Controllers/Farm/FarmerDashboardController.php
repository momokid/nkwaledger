<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Services\Ledger\Reports\IncomeAndExpenditure;
use App\Services\Ledger\Reports\IncomeAndExpenditureService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FarmerDashboardController extends Controller
{
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
                'livestock_count' => '0.00',
                'crop_unit_count' => 0,
                'breakdown' => $this->emptyBreakdown(),
                'filters' => ['from' => $from, 'to' => $to],
            ]);
        }

        // a farmer sees every record on their own books, confirmed or not
        $report = $this->incomes->for($farmer->id, $from, $to, includeProvisional: true);

        [$previousFrom, $previousTo] = $this->previousPeriod($from, $to);
        $previousReport = $this->incomes->for($farmer->id, $previousFrom, $previousTo, includeProvisional: true);

        return Inertia::render('Dashboard', [
            'summary' => $this->summaryFrom($report, $previousReport),
            'livestock_count' => $this->livestockCount($farmer->id),
            'crop_unit_count' => $this->cropUnitCount($farmer->id),
            'breakdown' => $this->breakdownFrom($report),
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

    private function livestockCount(int $farmerId): string
    {
        $unitIds = FarmUnit::query()
            ->where('farmer_profile_id', $farmerId)
            ->whereNotNull('approved_at')
            ->whereHas('farmType.category', fn($query) => $query->where('name', 'Livestock'))
            ->pluck('id');

        $total = FarmUnitStock::query()
            ->whereIn('farm_unit_id', $unitIds)
            ->whereNotNull('confirmed_at')
            ->whereNull('rejected_at')
            ->sum('current_quantity');

        return number_format((float) $total, 2, '.', '');
    }

    private function cropUnitCount(int $farmerId): int
    {
        return FarmUnit::query()
            ->where('farmer_profile_id', $farmerId)
            ->whereNotNull('approved_at')
            ->whereHas('farmType.category', fn($query) => $query->where('name', 'Crop'))
            ->count();
    }

    private function resolveFarmer(Request $request): ?FarmerProfile
    {
        return FarmerProfile::query()->where('user_id', $request->user()->id)->first();
    }
}
