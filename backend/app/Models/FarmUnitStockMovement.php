<?php

namespace App\Models;

use App\Enums\MovementReason;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'farm_unit_stock_id',
    'reason',
    'quantity',
    'is_increase',
    'occurred_on',
    'note',
    'recorded_by',
    'confirmed_at',
    'confirmed_by',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
])]

class FarmUnitStockMovement extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'reason' => MovementReason::class,
            'quantity' => 'decimal:2',
            'is_increase' => 'boolean',
            'occurred_on' => 'date',
            'confirmed_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // the reason decides the direction, except a correction which is told
        static::saving(function (FarmUnitStockMovement $movement) {
            if ((float) $movement->quantity <= 0) {
                throw new InvalidArgumentException('A movement needs a quantity above zero.');
            }

            if (! $movement->reason->needsDirection()) {
                $movement->is_increase = $movement->reason->addsToCount();
            }
        });

        static::saved(fn(FarmUnitStockMovement $movement) => $movement->stock?->refreshCount());
        static::deleted(fn(FarmUnitStockMovement $movement) => $movement->stock?->refreshCount());
        static::restored(fn(FarmUnitStockMovement $movement) => $movement->stock?->refreshCount());
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function isRejected(): bool
    {
        return $this->rejected_at !== null;
    }

    // whoever wrote the number down is not the one who checks it
    public function conflictedUserId(): ?int
    {
        return $this->recorded_by;
    }

    public function reject(int $userId, string $reason): void
    {
        if ($this->isConfirmed()) {
            throw new InvalidArgumentException('This change has already been checked.');
        }

        $this->forceFill([
            'rejected_at' => now(),
            'rejected_by' => $userId,
            'rejection_reason' => $reason,
        ])->save();
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at');
    }

    public function stock(): BelongsTo
    {
        return $this->belongsTo(FarmUnitStock::class, 'farm_unit_stock_id');
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
