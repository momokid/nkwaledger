<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'name',
    'type',
    'normal_balance',
    'is_system',
    'is_active',
])]
class LedgerAccount extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = ['asset', 'liability', 'equity', 'income', 'expense'];

    public const NORMAL_BALANCE_MAP = [
        'asset' => 'debit',
        'expense' => 'debit',
        'liability' => 'credit',
        'equity' => 'credit',
        'income' => 'credit',
    ];

    protected $attributes = [
        'is_system' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (LedgerAccount $account) {
            if (! in_array($account->type, self::TYPES, true)) {
                throw new InvalidArgumentException(
                    'Ledger account type must be one of: ' . implode(', ', self::TYPES)
                );
            }

            $account->normal_balance = self::NORMAL_BALANCE_MAP[$account->type];
        });
    }
}
