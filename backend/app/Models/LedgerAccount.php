<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use RuntimeException;

#[Fillable(['name', 'type_id', 'is_system', 'is_active'])]
class LedgerAccount extends Model
{
    use HasFactory, SoftDeletes;

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
        static::deleting(function (LedgerAccount $account) {
            if ($account->is_system) {
                throw new RuntimeException('System ledger accounts cannot be deleted.');
            }
        });
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LedgerAccountType::class, 'type_id');
    }

    // always reads live from the related type, never stored — so a type's debit/credit
    // setting can never drift out of sync with the accounts that reference it
    protected function normalBalance(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->type?->normal_balance,
        );
    }
}
