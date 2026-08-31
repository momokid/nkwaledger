<?php

namespace App\Services;

use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\ReversalRequest;

class ApprovalQueueService
{
    public function __construct(private readonly AccessControlService $access) {}

    // three separate reads joined in php, so each one uses its own index
    public function pending(User $user, int $limit = 200): Collection
    {
        $farmerIds = $this->reachableFarmerIds($user);

        return $this->units($user, $farmerIds, $limit)
            ->concat($this->stocks($user, $farmerIds, $limit))
            ->concat($this->movements($user, $farmerIds, $limit))
            ->concat($this->reversals($user, $farmerIds, $limit))
            // the thing waiting longest needs you most
            ->sortBy('waiting_since')
            ->values();
    }

    // only what this person can actually sign off
    public function countFor(User $user): int
    {
        return $this->pending($user)->where('can_approve', true)->count();
    }

    private function units(User $user, array $farmerIds, int $limit): Collection
    {
        if (! $this->access->can($user, 'farm-units.approve')) {
            return collect();
        }

        return FarmUnit::query()
            ->whereNull('approved_at')
            ->whereIn('farmer_profile_id', $farmerIds)
            ->with(['farmerProfile.user:id,surname,first_name', 'farmType:id,name', 'community:id,name', 'createdBy:id,surname'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn(FarmUnit $unit) => [
                'kind' => 'farm_unit',
                'id' => $unit->id,
                'farmer' => $this->farmerName($unit->farmerProfile),
                'farmer_id' => $unit->farmerProfile?->uuid,
                'what' => $unit->name,
                'added_by' => $unit->createdBy?->surname,
                'waiting_since' => $unit->created_at?->toIso8601String(),
                'can_approve' => $unit->conflictedUserId() !== $user->id,
                'details' => [
                    'farm_type' => $unit->farmType?->name,
                    'community' => $unit->community?->name,
                    'capacity' => $unit->capacity,
                    'capacity_unit' => $unit->capacity_unit,
                    // a unit with records piling up on it is the urgent one
                    'provisional_records' => Transaction::query()
                        ->where('farm_unit_id', $unit->id)
                        ->where('is_provisional', true)
                        ->count(),
                ],
            ]);
    }

    private function stocks(User $user, array $farmerIds, int $limit): Collection
    {
        if (! $this->access->can($user, 'farm-units.confirm')) {
            return collect();
        }

        return FarmUnitStock::query()
            ->whereNull('confirmed_at')
            ->whereHas('farmUnit', fn(Builder $query) => $query->whereIn('farmer_profile_id', $farmerIds))
            ->with(['farmUnit.farmerProfile.user:id,surname,first_name', 'recordedBy:id,surname'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn(FarmUnitStock $stock) => [
                'kind' => 'stock',
                'id' => $stock->id,
                'farmer' => $this->farmerName($stock->farmUnit?->farmerProfile),
                'farmer_id' => $stock->farmUnit?->farmerProfile?->uuid,
                'what' => "{$stock->opening_quantity} {$stock->unit_of_measure} in {$stock->farmUnit?->name}",
                'added_by' => $stock->recordedBy?->surname,
                'waiting_since' => $stock->created_at?->toIso8601String(),
                'can_approve' => $stock->conflictedUserId() !== $user->id,
                'unit_id' => $stock->farm_unit_id,
                'details' => [
                    'source' => $stock->source?->value,
                    'opening_quantity' => $stock->opening_quantity,
                    'current_quantity' => $stock->current_quantity,
                    'acquisition_cost' => $stock->acquisition_cost,
                    'started_on' => $stock->started_on?->toDateString(),
                    'unit_approved' => $stock->farmUnit?->isApproved(),
                ],
            ]);
    }

    private function movements(User $user, array $farmerIds, int $limit): Collection
    {
        if (! $this->access->can($user, 'farm-units.confirm')) {
            return collect();
        }

        return FarmUnitStockMovement::query()
            ->whereNull('confirmed_at')
            ->whereHas(
                'stock.farmUnit',
                fn(Builder $query) => $query->whereIn('farmer_profile_id', $farmerIds),
            )
            ->with(['stock.farmUnit.farmerProfile.user:id,surname,first_name', 'recordedBy:id,surname'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn(FarmUnitStockMovement $movement) => [
                'kind' => 'stock_movement',
                'id' => $movement->id,
                'farmer' => $this->farmerName($movement->stock?->farmUnit?->farmerProfile),
                'farmer_id' => $movement->stock?->farmUnit?->farmerProfile?->uuid,
                'what' => ($movement->is_increase ? '+' : '-')
                    . "{$movement->quantity} in {$movement->stock?->farmUnit?->name}",
                'added_by' => $movement->recordedBy?->surname,
                'waiting_since' => $movement->created_at?->toIso8601String(),
                'can_approve' => $movement->conflictedUserId() !== $user->id,
                'unit_id' => $movement->stock?->farm_unit_id,
                'stock_id' => $movement->farm_unit_stock_id,
                'details' => [
                    'reason' => $movement->reason?->label(),
                    'quantity' => $movement->quantity,
                    'is_increase' => $movement->is_increase,
                    'occurred_on' => $movement->occurred_on?->toDateString(),
                    'note' => $movement->note,
                    'count_now' => $movement->stock?->current_quantity,
                ],
            ]);
    }

    private function reversals(User $user, array $farmerIds, int $limit): Collection
    {
        if (! $this->access->can($user, 'approvals.view')) {
            return collect();
        }

        $mayApprove = $this->access->can($user, 'transactions.reverse-approve');

        return ReversalRequest::query()
            ->where('status', ReversalRequest::PENDING)
            ->whereHas('transaction', fn(Builder $query) => $query->whereIn('farmer_profile_id', $farmerIds))
            ->with(['transaction.farmerProfile.user:id,surname,first_name', 'transaction.template:id,name', 'requestedBy:id,surname'])
            ->orderBy('created_at')
            ->limit($limit)
            ->get()
            ->map(fn(ReversalRequest $request) => [
                'kind' => 'reversal',
                'id' => $request->id,
                'uuid' => $request->uuid,
                'farmer' => $this->farmerName($request->transaction?->farmerProfile),
                'farmer_id' => $request->transaction?->farmerProfile?->uuid,
                'what' => "Cancel {$request->transaction?->reference}",
                'added_by' => $request->requestedBy?->surname,
                'waiting_since' => $request->created_at?->toIso8601String(),
                'can_approve' => $mayApprove && (int) $request->requested_by !== $user->id,
                'details' => [
                    'reason' => $request->reason,
                    'reference' => $request->transaction?->reference,
                    'what_happened' => $request->transaction?->template?->name,
                    'amount' => $request->transaction?->amount_minor,
                    'recorded_on' => $request->transaction?->transaction_date?->toDateString(),
                ],
            ]);
    }

    // an agent works their own book, an admin sees the whole platform
    private function reachableFarmerIds(User $user): array
    {
        return FarmerProfile::query()
            ->when(! $user->hasRole('admin'), fn(Builder $query) => $query->where('assigned_agent_id', $user->id))
            ->pluck('id')
            ->all();
    }

    private function farmerName(?FarmerProfile $farmer): string
    {
        return trim("{$farmer?->user?->surname} {$farmer?->user?->first_name}");
    }
}
