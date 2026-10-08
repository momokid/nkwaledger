<?php

namespace App\Services\Ledger;

use App\Enums\MovementReason;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

// takes back the stock a cancelled record moved: its movements stop counting, so a sale or
// loss returns to the count and a purchase leaves it, never dropping a batch below zero
class StockReversal
{
    public const STOCK_USED = 'Some of the stock bought with this record has already been used, so it cannot be cancelled.';

    public function refusalFor(Transaction $original): ?string
    {
        foreach ($this->movementsOf($original) as $movement) {
            if ($movement->is_increase && $this->counts($movement) && (float) $movement->stock->current_quantity < (float) $movement->quantity) {
                return self::STOCK_USED;
            }
        }

        return null;
    }

    public function release(Transaction $original, User $by): void
    {
        foreach ($this->movementsOf($original) as $movement) {
            $movement->forceFill(['rejected_at' => now(), 'rejected_by' => $by->id])->save();

            if ($movement->reason === MovementReason::Opening) {
                $this->endIfEmpty($movement->stock, $movement);
            }
        }
    }

    // a batch this purchase started, now holding nothing and with no other history, is ended
    // (kept for the record, no longer active); any other movement or any stock left keeps it open
    private function endIfEmpty(FarmUnitStock $stock, FarmUnitStockMovement $opening): void
    {
        $stock->refresh();

        $hasOtherMovements = $stock->movements()->whereKeyNot($opening->id)->exists();

        if ($hasOtherMovements || (float) $stock->current_quantity > 0) {
            return;
        }

        $stock->forceFill(['ended_on' => now()->toDateString()])->save();
    }

    /** @return Collection<int, FarmUnitStockMovement> */
    private function movementsOf(Transaction $original): Collection
    {
        return FarmUnitStockMovement::query()
            ->with('stock')
            ->where('transaction_id', $original->id)
            ->whereNull('rejected_at')
            ->get();
    }

    // an unchecked purchase or opening count is not in the stock yet, so there is nothing to take out
    private function counts(FarmUnitStockMovement $movement): bool
    {
        return $movement->isConfirmed() || ! $movement->reason->mustBeConfirmedToCount();
    }
}
