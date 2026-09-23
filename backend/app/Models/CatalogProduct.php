<?php

namespace App\Models;

use App\Enums\BarcodeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'barcode',
    'barcode_type',
    'name',
    'category_id',
    'unit_id',
    'pack_quantity',
    'created_by',
    'seeded',
    'merged_into_id',
])]
class CatalogProduct extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'seeded' => false,
    ];

    protected function casts(): array
    {
        return [
            'barcode_type' => BarcodeType::class,
            'pack_quantity' => 'decimal:2',
            'seeded' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (CatalogProduct $product) {
            if ($product->uuid === null) {
                $product->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function hasBarcode(): bool
    {
        return $this->barcode !== null;
    }

    public function isMerged(): bool
    {
        return $this->merged_into_id !== null;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(ProductUnit::class, 'unit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function mergedInto(): BelongsTo
    {
        return $this->belongsTo(CatalogProduct::class, 'merged_into_id');
    }

    public function kioskProducts(): HasMany
    {
        return $this->hasMany(KioskProduct::class);
    }
}
