<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name',
    'normal_balance',
])]
class LedgerFundamentalType extends Model
{
    public const NAMES = ['Asset', 'Liability', 'Equity', 'Income', 'Expense'];

    public const NORMAL_BALANCE_MAP = [
        'Asset' => 'debit',
        'Expense' => 'debit',
        'Liability' => 'credit',
        'Equity' => 'credit',
        'Income' => 'credit',
    ];
}
