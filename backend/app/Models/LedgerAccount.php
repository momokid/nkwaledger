<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

#[Fillable([
    'name',
    'account_code',
    'control_id',
    'subcategory_id',
    'type_id',
    'is_system',
    'is_settlement',
    'is_active',
])]
class LedgerAccount extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'is_system' => false,
        'is_settlement' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_settlement' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (LedgerAccount $account) {
            if ($account->is_system) {
                throw new RuntimeException('System ledger accounts cannot be deleted.');
            }
        });
    }

    public function control(): BelongsTo
    {
        return $this->belongsTo(LedgerControl::class, 'control_id');
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(LedgerSubcategory::class, 'subcategory_id');
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LedgerType::class, 'type_id');
    }

    // the places a farmer's money can sit, and only the ones still in use
    public function scopeSettlement(Builder $query): Builder
    {
        return $query->where('is_settlement', true)->where('is_active', true);
    }

    // walks subcategory to category to class, never stored — so a category's Dr/Cr
    // setting can never drift out of sync with the accounts that sit beneath it
    protected function class(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->subcategory?->category?->class?->name,
        );
    }
}
