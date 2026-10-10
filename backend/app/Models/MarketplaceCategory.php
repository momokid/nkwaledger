<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

// a curated, admin-managed homepage section spanning BOTH kiosk products and produce
// listings - deliberately its own model rather than overloading ProductCategory,
// which is a single-select classification on a supplier's catalog product and already
// drives the farm-type suggestion boost; this is a many-to-many browse grouping with
// its own display rules (how many items, what order), a different concern entirely
#[Fillable(['name', 'slug', 'display_count', 'sort_order', 'is_active'])]
class MarketplaceCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'display_count' => 8,
        'sort_order' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'display_count' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function kioskProducts(): BelongsToMany
    {
        return $this->belongsToMany(KioskProduct::class, 'marketplace_category_kiosk_product');
    }

    public function produceListings(): BelongsToMany
    {
        return $this->belongsToMany(ProduceListing::class, 'marketplace_category_produce_listing');
    }
}
