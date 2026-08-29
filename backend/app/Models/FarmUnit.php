<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'farmer_profile_id',
    'farm_type_id',
    'community_id',
    'name',
    'capacity',
    'capacity_unit',
    'created_by',
    'approved_at',
    'approved_by',
    'is_active',
])]
class FarmUnit extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'approved_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // entries are blessed on the strength of an approval, so it cannot be taken back
        static::updating(function (FarmUnit $unit) {
            if ($unit->getOriginal('approved_at') !== null) {
                $unit->approved_at = $unit->getOriginal('approved_at');
                $unit->approved_by = $unit->getOriginal('approved_by');
            }
        });
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    // the agent who set up a pen is not the one who vouches that it exists
    public function conflictedUserId(): ?int
    {
        return $this->created_by;
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->whereNotNull('approved_at');
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(FarmUnitStock::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function farmType(): BelongsTo
    {
        return $this->belongsTo(FarmType::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
