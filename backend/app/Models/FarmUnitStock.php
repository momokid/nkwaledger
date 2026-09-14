<?php

namespace App\Models;

use App\Enums\MovementReason;
use App\Enums\StockSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'farm_unit_id',
    'source',
    'opening_quantity',
    'current_quantity',
    'unit_of_measure',
    'acquisition_cost',
    'started_on',
    'expected_ready_on',
    'ended_on',
    'recorded_by',
    'confirmed_at',
    'confirmed_by',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
])]

class FarmUnitStock extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'source' => StockSource::class,
            'opening_quantity' => 'decimal:2',
            'current_quantity' => 'decimal:2',
            'acquisition_cost' => 'decimal:2',
            'started_on' => 'date',
            'expected_ready_on' => 'date',
            'ended_on' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (FarmUnitStock $stock) {
            if ($stock->current_quantity === null) {
                $stock->current_quantity = $stock->opening_quantity;
            }
        });

        // the sum has to start somewhere, so the first batch writes its own movement. A
        // declared opening balance is trusted immediately; a bought one is held back the
        // same as any other purchase, until someone else checks it
        static::created(function (FarmUnitStock $stock) {
            $isPurchased = $stock->source === StockSource::Purchase;

            $stock->movements()->create([
                'reason' => MovementReason::Opening,
                'quantity' => $stock->opening_quantity,
                'is_increase' => true,
                'occurred_on' => $stock->started_on,
                'recorded_by' => $stock->recorded_by,
                'confirmed_at' => $isPurchased ? null : now(),
                'confirmed_by' => $isPurchased ? null : $stock->recorded_by,
            ]);
        });
    }

    // the count is the sum of every movement that still stands and is trusted; a birth or
    // purchase — including a purchased opening balance — sits out until someone else checks
    // it, so a farmer cannot inflate their own count
    public function refreshCount(): void
    {
        $isPurchasedBatch = $this->source === StockSource::Purchase;

        $total = $this->movements()
            ->whereNull('rejected_at')
            ->get(['quantity', 'is_increase', 'reason', 'confirmed_at'])
            ->reject(function (FarmUnitStockMovement $movement) use ($isPurchasedBatch) {
                if ($movement->isConfirmed()) {
                    return false;
                }

                return $movement->reason->mustBeConfirmedToCount()
                    || ($movement->reason === MovementReason::Opening && $isPurchasedBatch);
            })
            ->reduce(
                fn(float $carry, FarmUnitStockMovement $movement) => $movement->is_increase
                    ? $carry + (float) $movement->quantity
                    : $carry - (float) $movement->quantity,
                0.0,
            );

        $this->newQuery()->whereKey($this->getKey())->update([
            'current_quantity' => max($total, 0),
        ]);
    }

    public function isOpeningBalance(): bool
    {
        return $this->source === StockSource::OpeningBalance;
    }

    public function isOpen(): bool
    {
        return $this->ended_on === null;
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    public function conflictedUserId(): ?int
    {
        return $this->recorded_by;
    }

    // an agent's own entry can never be waved through by another agent — only admin may
    public function requiresAdminToApprove(): bool
    {
        return $this->recordedBy?->hasRole('agent') ?? false;
    }

    // a checked number in an unchecked pen proves nothing, so both must pass
    public function countsTowardCredit(): bool
    {
        return $this->isConfirmed() && (bool) $this->farmUnit?->isApproved();
    }

    // rejecting a batch means the opening number never stood, so its own movement goes with it
    public function reject(int $userId, string $reason): void
    {
        if ($this->isConfirmed()) {
            throw new InvalidArgumentException('This count has already been checked.');
        }

        $this->forceFill([
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
        ])->save();

        $this->movements()
            ->where('reason', MovementReason::Opening)
            ->first()
            ?->forceFill([
                'rejected_at' => now(),
                'rejected_by' => $userId,
                'rejection_reason' => $reason,
            ])
            ->save();
    }

    // what the farmer paid, spread across what is left today
    public function costPerUnit(): ?string
    {
        $left = (float) $this->current_quantity;

        if ($left <= 0) {
            return null;
        }

        return number_format((float) $this->acquisition_cost / $left, 2, '.', '');
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function movements(): HasMany
    {
        return $this->hasMany(FarmUnitStockMovement::class, 'farm_unit_stock_id');
    }

    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
