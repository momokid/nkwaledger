<?php

namespace App\Models;

use App\Enums\PriceHistoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

#[Fillable(['kiosk_product_id', 'old_price', 'new_price', 'changed_by', 'type'])]
class KioskProductPriceHistory extends Model
{
    use HasFactory;

    // a record of what happened has no later state to track
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        // prices are never overwritten - a correction is a new row, never an edit
        static::updating(function () {
            throw new RuntimeException('A price history entry cannot be changed once written.');
        });

        static::deleting(function () {
            throw new RuntimeException('A price history entry cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'type' => PriceHistoryType::class,
            'created_at' => 'datetime',
        ];
    }

    public function kioskProduct(): BelongsTo
    {
        return $this->belongsTo(KioskProduct::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
