<?php

namespace App\Models;

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use RuntimeException;

#[Fillable([
    'kiosk_id',
    'farmer_profile_id',
    'farm_unit_id',
    'facilitating_agent_id',
    'payment_method',
    'status',
    'amount_minor',
    'requested_at',
    'confirmed_at',
    'received_at',
    'closed_at',
    'ledger_transaction_id',
])]
class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'requested',
        'amount_minor' => 0,
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => OrderPaymentMethod::class,
            'status' => OrderStatus::class,
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if ($order->uuid === null) {
                $order->uuid = (string) Str::uuid7();
            }
        });

        // the number is the row's own id, dressed up - permanent and sequential because
        // the id already is, mirroring how a kiosk's own NKL-#### number is assigned
        static::created(function (Order $order) {
            $order->forceFill(['order_number' => sprintf('NKL-ORD-%06d', $order->id)])->saveQuietly();
        });

        static::updating(function (Order $order) {
            if ($order->isDirty('order_number') && $order->getOriginal('order_number') !== null) {
                throw new RuntimeException('An order number cannot be changed once assigned.');
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    // the display-only rollup of whichever taps have happened so far - never the source
    // of truth for whether the order is complete; see isFullyConfirmed() for that
    public function recomputeStatus(): void
    {
        $this->status = match (true) {
            $this->closed_at !== null => OrderStatus::Closed,
            $this->received_at !== null => OrderStatus::Received,
            $this->confirmed_at !== null => OrderStatus::Confirmed,
            default => OrderStatus::Requested,
        };
    }

    // the actual two-tap gate: both the supplier's confirm and the farmer's receive have
    // landed, in either order, and the order never timed out - this is what everything
    // that "counts" (the ledger post, review eligibility, sales_count) checks, never status
    public function isFullyConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->received_at !== null && $this->closed_at === null;
    }

    public function kiosk(): BelongsTo
    {
        return $this->belongsTo(Kiosk::class);
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }

    public function facilitatingAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'facilitating_agent_id');
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'ledger_transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class);
    }
}
