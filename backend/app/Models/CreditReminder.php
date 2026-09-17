<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'transaction_id',
])]
class CreditReminder extends Model
{
    // a reminder is a fact that happened, never edited afterward
    public const UPDATED_AT = null;

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }
}
