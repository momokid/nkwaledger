<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'order_id',
    'kiosk_product_id',
    'quantity',
    'unit_price_at_order_time',
])]
class OrderItem extends Model
{
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function kioskProduct(): BelongsTo
    {
        return $this->belongsTo(KioskProduct::class);
    }

    public function lineTotalMinor(): int
    {
        return $this->quantity * $this->unit_price_at_order_time;
    }
}
