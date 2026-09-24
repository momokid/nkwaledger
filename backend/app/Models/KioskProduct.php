<?php

namespace App\Models;

use App\Enums\KioskProductStatus;
use App\Enums\PriceHistoryType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'kiosk_id',
    'catalog_product_id',
    'price',
    'in_stock',
    'expiry_date',
    'batch_number',
    'price_confirmed_at',
    'stale_alerted_at',
    'expiry_alerted_at',
    'status',
])]
class KioskProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'in_stock' => true,
        'status' => KioskProductStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'status' => KioskProductStatus::class,
            'price' => 'integer',
            'in_stock' => 'boolean',
            'expiry_date' => 'date',
            'price_confirmed_at' => 'datetime',
            'stale_alerted_at' => 'datetime',
            'expiry_alerted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (KioskProduct $product) {
            if ($product->uuid === null) {
                $product->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null && $this->expiry_date->isPast();
    }

    public function isPriceStale(int $staleDays): bool
    {
        if ($this->price_confirmed_at === null) {
            return true;
        }

        return $this->price_confirmed_at->lte(now()->subDays($staleDays));
    }

    // visible to a farmer search: in stock, not suspended, and not expired -
    // checked live at query time, never relying only on a nightly job
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('in_stock', true)
            ->where('status', KioskProductStatus::Active)
            ->where(function (Builder $q) {
                $q->whereNull('expiry_date')->orWhere('expiry_date', '>=', now()->toDateString());
            });
    }

    // the record's first price is a "change" from nothing, same as any other
    public function recordInitialPrice(?User $by): void
    {
        $this->priceHistory()->create([
            'old_price' => null,
            'new_price' => $this->price,
            'changed_by' => $by?->id,
            'type' => PriceHistoryType::Changed,
        ]);

        $this->forceFill(['price_confirmed_at' => now()])->saveQuietly();
    }

    public function changePrice(int $newPrice, ?User $by): void
    {
        $oldPrice = $this->price;

        $this->priceHistory()->create([
            'old_price' => $oldPrice,
            'new_price' => $newPrice,
            'changed_by' => $by?->id,
            'type' => PriceHistoryType::Changed,
        ]);

        $this->update([
            'price' => $newPrice,
            'price_confirmed_at' => now(),
            'stale_alerted_at' => null,
        ]);
    }

    public function confirmPriceUnchanged(?User $by): void
    {
        $this->priceHistory()->create([
            'old_price' => $this->price,
            'new_price' => $this->price,
            'changed_by' => $by?->id,
            'type' => PriceHistoryType::Confirmed,
        ]);

        $this->update([
            'price_confirmed_at' => now(),
            'stale_alerted_at' => null,
        ]);
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function catalogProduct(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class);
    }

    public function priceHistory(): HasMany
    {
        return $this->hasMany(KioskProductPriceHistory::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(KioskProductImage::class);
    }
}
