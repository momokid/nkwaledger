<?php

namespace App\Models;

use App\Enums\KioskReportStatus;
use App\Enums\KioskStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

#[Fillable([
    'supplier_id',
    'name',
    'thumbnail_path',
    'latitude',
    'longitude',
    'region_id',
    'district_id',
    'contact_phone',
    'status',
    'confirmed_at',
    'requires_admin_approval',
    'admin_approved_at',
    'admin_approved_by',
])]
class Kiosk extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => KioskStatus::PendingConfirmation->value,
        'requires_admin_approval' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => KioskStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'confirmed_at' => 'datetime',
            'requires_admin_approval' => 'boolean',
            'admin_approved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Kiosk $kiosk) {
            if ($kiosk->uuid === null) {
                $kiosk->uuid = (string) Str::uuid7();
            }
        });

        // the number is the row's own id, dressed up - permanent and sequential because the id already is
        static::created(function (Kiosk $kiosk) {
            $kiosk->forceFill(['kiosk_number' => sprintf('NKL-%04d', $kiosk->id)])->saveQuietly();
        });

        static::updating(function (Kiosk $kiosk) {
            if ($kiosk->isDirty('kiosk_number') && $kiosk->getOriginal('kiosk_number') !== null) {
                throw new RuntimeException('A kiosk number cannot be changed once assigned.');
            }
        });
    }

    // urls carry the uuid, so a kiosk cannot be found by counting upward
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null;
    }

    public function needsAdminApproval(): bool
    {
        return $this->requires_admin_approval && $this->admin_approved_at === null;
    }

    // everything this kiosk needs before it can go live: its own confirmation, plus admin sign-off if it was over the cap
    public function isFullyConfirmed(): bool
    {
        return $this->isConfirmed() && ! $this->needsAdminApproval();
    }

    public function isVisible(): bool
    {
        return $this->status === KioskStatus::Active;
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', KioskStatus::Active);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function adminApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_approved_by');
    }

    // named to match the {kioskProduct} route parameter, so Laravel's implicit
    // route-model binding scopes the child lookup through this kiosk automatically
    public function kioskProducts(): HasMany
    {
        return $this->hasMany(KioskProduct::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(KioskReport::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // every review left on this kiosk, reached through its orders - an order only ever
    // gets a review once it is fully confirmed (see OrderService::review()), so this
    // never needs its own status filter
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(Review::class, Order::class, 'kiosk_id', 'order_id');
    }

    // several different farmers, not just several reports from one - a repeat reporter
    // does not raise this, since it is the same complaint admin already knows about
    public function hasUrgentReports(): bool
    {
        return $this->reports()
            ->whereIn('status', [KioskReportStatus::Open, KioskReportStatus::SupplierAnswered, KioskReportStatus::WithAdmin])
            ->distinct('farmer_profile_id')
            ->count('farmer_profile_id') > 1;
    }
}
