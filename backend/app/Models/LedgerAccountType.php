<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;
use RuntimeException;

#[Fillable(['name', 'normal_balance'])]
class LedgerAccountType extends Model
{
    use HasFactory, SoftDeletes;

    public const NORMAL_BALANCES = ['debit', 'credit'];

    protected static function booted(): void
    {
        static::saving(function (LedgerAccountType $type) {
            if (! in_array($type->normal_balance, self::NORMAL_BALANCES, true)) {
                throw new InvalidArgumentException(
                    'Normal balance must be one of: ' . implode(', ', self::NORMAL_BALANCES)
                );
            }
        });

        static::deleting(function (LedgerAccountType $type) {
            if ($type->ledgerAccounts()->exists()) {
                throw new RuntimeException(
                    'Cannot delete a ledger account type that is still assigned to one or more ledger accounts.'
                );
            }
        });
    }

    public function ledgerAccounts(): HasMany
    {
        return $this->hasMany(LedgerAccount::class, 'type_id');
    }
}
