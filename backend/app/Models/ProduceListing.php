<?php

namespace App\Models;

use App\Enums\ProduceListingStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'farm_unit_stock_id',
    'farmer_profile_id',
    'posted_by_user_id',
    'status',
    'quantity_listed',
    'quantity_remaining',
    'photo',
    'crop_expiry_days',
    'expires_at',
    'expiry_reminder_sent_at',
    'still_available_prompted_at',
    'farmer_agreed_at',
])]
class ProduceListing extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProduceListingStatus::class,
            'quantity_listed' => 'decimal:2',
            'quantity_remaining' => 'decimal:2',
            'crop_expiry_days' => 'integer',
            'expires_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'still_available_prompted_at' => 'datetime',
            'farmer_agreed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProduceListing $listing) {
            if ($listing->uuid === null) {
                $listing->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isDraft(): bool
    {
        return $this->status === ProduceListingStatus::Draft;
    }

    public function isActive(): bool
    {
        return $this->status === ProduceListingStatus::Active;
    }

    // crops wear out, livestock and fish do not - the category name is the only
    // signal there is, matching FarmTypeSeeder's own 'Crop'/'Livestock'/'Aquatic' split
    public function isCrop(): bool
    {
        return $this->farmUnitStock?->farmUnit?->farmType?->category?->name === 'Crop';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ProduceListingStatus::Active);
    }

    public function farmUnitStock(): BelongsTo
    {
        return $this->belongsTo(FarmUnitStock::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(ProduceSale::class);
    }
}
