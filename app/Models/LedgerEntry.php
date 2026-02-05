<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LedgerEntry extends Model
{
    protected $fillable = [
        'ledger_transaction_id',
        'account_id',
        'entry_type',
        'amount',
    ];

    public function transaction()
    {
        return $this->belongsTo(LedgerTransaction::class);
    }
}
