<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
    'settlement_transaction_id',
    'amount_minor',
])]
class CreditSettlement extends Model
{
    // a settlement is a fact that happened, never edited afterward
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
        ];
    }

    // the original sale/purchase this pays down
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    // the "Payment received"/"Payment made" transaction that pays it
    public function settlementTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'settlement_transaction_id');
    }
}
