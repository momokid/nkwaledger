<?php

namespace App\Http\Controllers\Farm;

use App\Enums\MovementReason;
use App\Enums\StockSource;
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
                'capacity' => $unit->capacity === null ? null : $this->trimmedQuantity((float) $unit->capacity),
                'capacity_unit' => $unit->capacity_unit,
                'is_approved' => $unit->isApproved(),
                'analysis' => $this->analysisFor($farmer->id, $unit->id, $from, $to),
                'timeline' => $this->timelineFor($unit),
                'stocks' => $unit->stocks
                    ->sortByDesc('started_on')
                    ->values()
                    ->map(fn(FarmUnitStock $stock) => [
                        'id' => $stock->id,
                        'source' => $stock->source->label(),
                        'opening_quantity' => $this->trimmedQuantity((float) $stock->opening_quantity),
                        'current_quantity' => $this->trimmedQuantity((float) $stock->current_quantity),
                        'unit_of_measure' => $stock->unit_of_measure,
                        'started_on' => $stock->started_on?->toDateString(),
                        'expected_ready_on' => $stock->expected_ready_on?->toDateString(),
                        'ended_on' => $stock->ended_on?->toDateString(),
                        'is_confirmed' => $stock->isConfirmed(),
                        'is_rejected' => $stock->isRejected(),
                        'rejection_reason' => $stock->rejection_reason,
                        'movements' => $stock->movements
                            ->sort(function ($a, $b) {
                                // the starting count anchors the story at the top, everything else lands newest first
                                if ($a->reason === MovementReason::Opening) {
                                    return -1;
                                }

                                if ($b->reason === MovementReason::Opening) {
                                    return 1;
                                }

                                return $b->occurred_on <=> $a->occurred_on;
                            })
                            ->values()
                            ->map(fn($movement) => [
                                'id' => $movement->id,
                                'reason' => $movement->reason?->label(),
                                'quantity' => $this->trimmedQuantity((float) $movement->quantity),
                                'is_increase' => $movement->is_increase,
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
            'produce_quantity_sold' => $this->trimmedQuantity((float) $quantitySold),
        ];
    }

    // one flat, chronological line per event, across every batch, with a running total;
    // the farmer never needs to think in batches
    private function timelineFor(FarmUnit $unit): array
    {
        $movements = FarmUnitStockMovement::query()
            ->whereIn('farm_unit_stock_id', $unit->stocks->pluck('id'))
            ->with('stock')
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get();

        $runningTotal = 0.0;
        $seenFirstOpening = false;
        $timeline = [];

        foreach ($movements as $movement) {
            $isOpening = $movement->reason === MovementReason::Opening;
            $isVeryFirst = $isOpening && ! $seenFirstOpening;

            if ($isOpening) {
                $seenFirstOpening = true;
            }

            if (! $movement->isRejected()) {
                $runningTotal += $movement->is_increase
                    ? (float) $movement->quantity
                    : -(float) $movement->quantity;
            }

            $timeline[] = [
                'id' => $movement->id,
                'label' => $this->timelineLabel($movement, $isVeryFirst),
                'quantity' => $this->trimmedQuantity((float) $movement->quantity),
                'is_increase' => $movement->is_increase,
                'occurred_on' => $movement->occurred_on?->toDateString(),
                'expected_ready_on' => $isOpening ? $movement->stock?->expected_ready_on?->toDateString() : null,
                'running_total' => $this->trimmedQuantity(max($runningTotal, 0)),
                'is_confirmed' => $movement->isConfirmed(),
                'is_rejected' => $movement->isRejected(),
                'rejection_reason' => $movement->rejection_reason,
            ];
        }

        return $timeline;
    }

    private function timelineLabel(FarmUnitStockMovement $movement, bool $isVeryFirst): string
    {
        if ($movement->reason === MovementReason::Opening) {
            if ($isVeryFirst) {
                return 'Starts with';
            }

            return $movement->stock?->source === StockSource::Purchase ? 'Bought' : 'Added';
        }

        return match ($movement->reason) {
            MovementReason::Sale => 'Sold',
            MovementReason::Loss => 'Lost',
            MovementReason::Birth => 'Birth',
            MovementReason::Purchase => 'Bought',
            MovementReason::Theft => 'Stolen',
            MovementReason::Death => 'Died',
            MovementReason::Cull => 'Culled',
            MovementReason::Correction => 'Corrected',
            default => 'Update',
        };
    }

    // whole numbers read as "36", but a weighed or measured amount keeps its fraction;
    // commas make a big count (like 230,000 birds) readable at a glance
    private function trimmedQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 2, '.', ','), '0'), '.');
    }

    // the farmer's own page names nobody, and only their own profile answers
    private function resolveFarmer(Request $request): FarmerProfile
    {
        $own = FarmerProfile::query()->where('user_id', $request->user()->id)->first();

        abort_if($own === null, 403);

        return $own;
    }
}
