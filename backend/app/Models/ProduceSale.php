<?php

namespace App\Models;

use App\Enums\OrderPaymentMethod;
use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

// the buyer-order path for a produce listing - structurally Step 5's Order, seller
// and buyer swapped: the farmer is the seller here, so their own confirm plays the
// role a kiosk supplier's confirm played, and the buyer's receive is the other tap
#[Fillable([
    'produce_listing_id',
    'buyer_user_id',
    'farmer_profile_id',
    'quantity',
    'payment_method',
    'amount_minor',
    'status',
    'requested_at',
    'confirmed_at',
    'received_at',
    'closed_at',
    'ledger_transaction_id',
    'agent_id',
    'agent_co_confirmed_at',
])]
class ProduceSale extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => 'requested',
    ];

    protected function casts(): array
    {
        return [
            'payment_method' => OrderPaymentMethod::class,
            'status' => OrderStatus::class,
            'quantity' => 'decimal:2',
            'requested_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'received_at' => 'datetime',
            'closed_at' => 'datetime',
            'agent_co_confirmed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (ProduceSale $sale) {
            if ($sale->uuid === null) {
                $sale->uuid = (string) Str::uuid7();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function recomputeStatus(): void
    {
        $this->status = match (true) {
            $this->closed_at !== null => OrderStatus::Closed,
            $this->received_at !== null => OrderStatus::Received,
            $this->confirmed_at !== null => OrderStatus::Confirmed,
            default => OrderStatus::Requested,
        };
    }

    // the same two-tap gate Order::isFullyConfirmed() checks, mirrored here since
    // this is a distinct model - see the Step 7 audit for why it is not just Order
    public function isFullyConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->received_at !== null && $this->closed_at === null;
    }

    // mirrors FarmUnitStock::countsTowardCredit() - a computed gate, not a stored
    // exclusion flag: posted is not enough, an agent must have vouched for it too
    public function countsTowardCredit(): bool
    {
        return $this->isFullyConfirmed() && $this->agent_co_confirmed_at !== null;
    }

    public function produceListing(): BelongsTo
    {
        return $this->belongsTo(ProduceListing::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_user_id');
    }

    public function farmerProfile(): BelongsTo
    {
        return $this->belongsTo(FarmerProfile::class);
    }

    public function ledgerTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'ledger_transaction_id');
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
