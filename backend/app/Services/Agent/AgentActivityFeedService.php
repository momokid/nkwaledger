<?php

namespace App\Services\Agent;

use App\Models\FarmUnitStockMovement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgentActivityFeedService
{
    public function __construct(
        private readonly FarmerRosterService $roster,
    ) {}

    // only farmers with income or expense in this period are "active" enough to
    // surface here — the same definition the dashboard roster uses, so a farmer
    // who only lost livestock this period stays out of both places consistently
    public function recentFor(int $agentId, string $from, string $to, int $limit = 5): array
    {
        $activeFarmers = $this->activeFarmers($agentId, $from, $to);

        if ($activeFarmers->isEmpty()) {
            return [];
        }

        $entries = $this->transactionEntries($activeFarmers, $limit)
            ->merge($this->movementEntries($activeFarmers, $limit))
            ->all();

        usort($entries, fn($a, $b) => $b['sort_at']->timestamp <=> $a['sort_at']->timestamp
            ?: $b['sort_id'] <=> $a['sort_id']);

        return collect($entries)
            ->take($limit)
            ->map(fn($entry) => collect($entry)->except(['sort_at', 'sort_id'])->all())
            ->values()
            ->all();
    }

    // keyed by internal id so both queries below can resolve a name without a
    // second round trip per row
    private function activeFarmers(int $agentId, string $from, string $to): Collection
    {
        [,,, $rows] = $this->roster->totalsFor($agentId, $from, $to, withRows: true);

        return collect($rows)
            ->where('status', 'active')
            ->pluck('name', 'farmer_profile_id');
    }

    private function transactionEntries(Collection $activeFarmers, int $limit): Collection
    {
        return Transaction::query()
            ->whereIn('farmer_profile_id', $activeFarmers->keys())
            ->whereIn('transaction_type', [Transaction::INCOME, Transaction::EXPENSE, Transaction::LOSS])
            ->with('template:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn(Transaction $transaction) => [
                'kind' => 'transaction',
                'farmer' => $activeFarmers->get($transaction->farmer_profile_id),
                'action' => match ($transaction->transaction_type) {
                    Transaction::INCOME => 'Logged income',
                    Transaction::EXPENSE => 'Logged expense',
                    default => 'Recorded a loss',
                },
                'detail' => $transaction->template?->name,
                'amount_minor' => $transaction->amount_minor,
                'is_income' => $transaction->transaction_type === Transaction::INCOME,
                'occurred_at' => $transaction->created_at->toIso8601String(),
                'sort_at' => $transaction->created_at,
                'sort_id' => $transaction->id,
            ]);
    }

    private function movementEntries(Collection $activeFarmers, int $limit): Collection
    {
        return FarmUnitStockMovement::query()
            ->whereNull('rejected_at')
            ->whereHas(
                'stock.farmUnit',
                fn(Builder $query) => $query->whereIn('farmer_profile_id', $activeFarmers->keys()),
            )
            ->with('stock.farmUnit:id,farmer_profile_id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn(FarmUnitStockMovement $movement) => [
                'kind' => 'stock_movement',
                'farmer' => $activeFarmers->get($movement->stock?->farmUnit?->farmer_profile_id),
                'reason' => $movement->reason->label(),
                'farm_unit' => $movement->stock?->farmUnit?->name,
                'quantity' => $movement->quantity,
                'is_increase' => $movement->is_increase,
                'occurred_at' => $movement->created_at->toIso8601String(),
                'sort_at' => $movement->created_at,
                'sort_id' => $movement->id,
            ]);
    }
}
