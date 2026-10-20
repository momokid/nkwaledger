<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'farm_type_category_id',
    'name',
    'sort_order',
    'is_active',
])]
class HealthCategory extends Model
{
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (HealthCategory $category) {
            $category->uuid ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function scopeActiveFor(Builder $query, FarmTypeCategory $farmCategory): Builder
    {
        return $query->where('farm_type_category_id', $farmCategory->id)
            ->where('is_active', true)
            ->orderBy('sort_order');
    }

    public function farmTypeCategory(): BelongsTo
    {
        return $this->belongsTo(FarmTypeCategory::class);
    }
}
