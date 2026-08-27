<?php

namespace App\Models;

use App\Enums\StockSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'farm_unit_id',
    'source',
    'opening_quantity',
    'current_quantity',
    'unit_of_measure',
    'acquisition_cost',
    'started_on',
    'ended_on',
    'recorded_by',
    'confirmed_at',
    'confirmed_by',
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
            'ended_on' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // a new batch starts with everything it came with
        static::creating(function (FarmUnitStock $stock) {
            if ($stock->current_quantity === null) {
                $stock->current_quantity = $stock->opening_quantity;
            }
        });
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

    // whoever wrote the number down is not the one who checks it
    public function conflictedUserId(): ?int
    {
        return $this->recorded_by;
    }

    // a checked number in an unchecked pen proves nothing, so both must pass
    public function countsTowardCredit(): bool
    {
        return $this->isConfirmed() && (bool) $this->farmUnit?->isApproved();
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
