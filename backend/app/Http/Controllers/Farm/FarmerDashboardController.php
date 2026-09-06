<?php

namespace App\Http\Controllers\Farm;

use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class FarmerDashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $from = $request->query('from', Carbon::now()->subDays(29)->toDateString());
        $to = $request->query('to', Carbon::now()->toDateString());

        return Inertia::render('Dashboard', [
            'summary' => $farmer ? $this->summaryFor($farmer->id, $from, $to) : $this->emptySummary(),
            'livestock_count' => $farmer ? $this->livestockCount($farmer->id) : '0.00',
            'crop_unit_count' => $farmer ? $this->cropUnitCount($farmer->id) : 0,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
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

    private function summaryFor(int $farmerId, string $from, string $to): array
    {
        [$income, $expense] = $this->incomeAndExpenseFor($farmerId, $from, $to);
        $net = $income - $expense;

        [$previousFrom, $previousTo] = $this->previousPeriod($from, $to);
        [$prevIncome, $prevExpense] = $this->incomeAndExpenseFor($farmerId, $previousFrom, $previousTo);
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

    private function incomeAndExpenseFor(int $farmerId, string $from, string $to): array
    {
        $totals = Transaction::query()
            ->where('farmer_profile_id', $farmerId)
            ->whereDate('transaction_date', '>=', $from)
            ->whereDate('transaction_date', '<=', $to)
            ->selectRaw('transaction_type, SUM(amount_minor) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        return [(int) ($totals['INCOME'] ?? 0), (int) ($totals['EXPENSE'] ?? 0)];
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
