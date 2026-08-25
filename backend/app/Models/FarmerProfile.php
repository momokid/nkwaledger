<?php

namespace App\Models;

use App\Enums\IdentityType;
use App\Support\IdentityDocument;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id',
    'gender',
    'date_of_birth',
    'community_id',
    'farmer_group_id',
    'identity_type',
    'identity_number',
    'identity_number_hash',
    'identity_verified_at',
    'identity_verified_by',
    'registered_by',
    'onboarded_at',
    'opening_balance_posted_at',
    'is_active',
])]
class FarmerProfile extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'identity_type' => IdentityType::class,
            'date_of_birth' => 'date',
            'identity_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'opening_balance_posted_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    // write only, the raw number is hashed on the way in and can never be read back
    protected function identityNumber(): Attribute
    {
        return Attribute::make(
            get: fn() => null,
            set: fn(?string $value) => [
                'identity_number_hash' => filled($value) ? IdentityDocument::hash($value) : null,
            ],
        );
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }

    public function farmerGroup(): BelongsTo
    {
        return $this->belongsTo(FarmerGroup::class);
    }

    public function farmTypes(): BelongsToMany
    {
        return $this->belongsToMany(FarmType::class, 'farmer_farm_types');
    }

    public function identityVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'identity_verified_by');
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }
}
