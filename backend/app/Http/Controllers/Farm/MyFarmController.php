<?php

namespace App\Http\Controllers\Farm;

use App\Enums\MovementReason;
use App\Http\Controllers\Controller;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class MyFarmController extends Controller
{
    public function index(Request $request): Response
    {
        $farmer = $this->resolveFarmer($request);

        $from = $request->query('from', Carbon::now()->startOfYear()->toDateString());
        $to = $request->query('to', Carbon::now()->endOfYear()->toDateString());

        $units = FarmUnit::query()
            ->where('farmer_profile_id', $farmer->id)
            ->with([
                'farmType.category:id,name',
                'stocks.movements.recordedBy:id,surname',
            ])
            ->orderByDesc('id')
            ->get()
            ->map(fn(FarmUnit $unit) => [
                'id' => $unit->id,
                'name' => $unit->name,
                'farm_type' => $unit->farmType?->name,
                'farm_type_category' => $unit->farmType?->category?->name,
                'capacity' => $unit->capacity,
                'capacity_unit' => $unit->capacity_unit,
                'is_approved' => $unit->isApproved(),
                'analysis' => $this->analysisFor($farmer->id, $unit->id, $from, $to),
                'stocks' => $unit->stocks
                    ->sortByDesc('started_on')
                    ->values()
                    ->map(fn(FarmUnitStock $stock) => [
                        'id' => $stock->id,
                        'source' => $stock->source->label(),
                        'opening_quantity' => $stock->opening_quantity,
                        'current_quantity' => $stock->current_quantity,
                        'unit_of_measure' => $stock->unit_of_measure,
                        'started_on' => $stock->started_on?->toDateString(),
                        'expected_ready_on' => $stock->expected_ready_on?->toDateString(),
                        'ended_on' => $stock->ended_on?->toDateString(),
                        'is_confirmed' => $stock->isConfirmed(),
                        'is_rejected' => $stock->isRejected(),
                        'rejection_reason' => $stock->rejection_reason,
                        'movements' => $stock->movements
                            ->sortByDesc('occurred_on')
                            ->values()
                            ->map(fn($movement) => [
                                'id' => $movement->id,
                                'reason' => $movement->reason?->label(),
                                'quantity' => $movement->quantity,
                                'occurred_on' => $movement->occurred_on?->toDateString(),
                                'recorded_by' => $movement->recordedBy?->surname,
                                'is_confirmed' => $movement->isConfirmed(),
                                'is_rejected' => $movement->isRejected(),
                                'rejection_reason' => $movement->rejection_reason,
                            ]),
                    ]),
            ]);

        return Inertia::render('MyFarm/Index', [
            'units' => $units,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    // gross figures for one pen, over the range the farmer is looking at
    private function analysisFor(int $farmerId, int $farmUnitId, string $from, string $to): array
    {
        $totals = Transaction::query()
            ->where('farmer_profile_id', $farmerId)
            ->where('farm_unit_id', $farmUnitId)
            ->whereBetween('transaction_date', [$from, $to])
            ->selectRaw('transaction_type, SUM(amount_minor) as total')
            ->groupBy('transaction_type')
            ->pluck('total', 'transaction_type');

        $income = (int) ($totals['INCOME'] ?? 0);
        $expense = (int) ($totals['EXPENSE'] ?? 0);
        $loss = (int) ($totals['LOSS'] ?? 0);

        $quantitySold = FarmUnitStockMovement::query()
            ->whereIn('farm_unit_stock_id', FarmUnitStock::query()->where('farm_unit_id', $farmUnitId)->pluck('id'))
            ->where('reason', MovementReason::Sale)
            ->whereBetween('occurred_on', [$from, $to])
            ->whereNull('rejected_at')
            ->sum('quantity');

        return [
            'total_income' => $income,
            'total_expense' => $expense,
            'total_loss' => $loss,
            'net' => $income - $expense,
            'produce_quantity_sold' => number_format((float) $quantitySold, 2, '.', ''),
        ];
    }

    // the farmer's own page names nobody, and only their own profile answers
    private function resolveFarmer(Request $request): FarmerProfile
    {
        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }
}
