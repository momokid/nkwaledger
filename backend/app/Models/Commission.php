<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'order_id',
    'agent_id',
    'status',
    'verifies_farmer',
])]
class Commission extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'status' => CommissionStatus::PendingAdmin->value,
        'verifies_farmer' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => CommissionStatus::class,
            'verifies_farmer' => 'boolean',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'agent_id');
    }
}
