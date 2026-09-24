<?php

namespace App\Models;

use App\Enums\SupplierAccountStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

#[Fillable([
    'user_id',
    'business_name',
    'business_registration_number',
    'id_number_hash',
    'id_photo_path',
    'id_verified_at',
    'id_verified_by',
    'email',
    'email_verified_at',
    'account_status',
    'suspended_at',
    'suspended_by',
    'suspension_reason',
])]
class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'account_status' => SupplierAccountStatus::Active->value,
    ];

    protected function casts(): array
    {
        return [
            'account_status' => SupplierAccountStatus::class,
            'email_verified_at' => 'datetime',
            'id_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if ($supplier->uuid === null) {
                $supplier->uuid = (string) Str::uuid7();
            }
        });
    }

    // urls carry the uuid, so a supplier cannot be found by counting upward
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isEmailVerified(): bool
    {
        return $this->email_verified_at !== null;
    }

    public function isPhoneVerified(): bool
    {
        return $this->user?->phone_verified_at !== null;
    }

    // what the 1-kiosk cap counts by: an unproven email or phone cannot stand in for a proven identity
    public function isVerifiedIdentity(): bool
    {
        return $this->isEmailVerified() && $this->isPhoneVerified();
    }

    public function isSuspended(): bool
    {
        return $this->account_status === SupplierAccountStatus::Suspended;
    }

    // ID and business-registration checks are postponed; this reads only what already exists
    public function verificationStatus(): string
    {
        if ($this->id_verified_at !== null) {
            return 'id_verified';
        }

        if ($this->isVerifiedIdentity()) {
            return 'email_and_phone_verified';
        }

        return 'unverified';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function kiosks(): HasMany
    {
        return $this->hasMany(Kiosk::class);
    }

    public function idVerifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_verified_by');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }
}
