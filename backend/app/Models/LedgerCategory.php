<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'fundamental_type_id',
    'name',
    'type',
])]
class LedgerCategory extends Model
{
    use HasFactory, SoftDeletes;

    public function fundamentalType(): BelongsTo
    {
        return $this->belongsTo(LedgerFundamentalType::class);
    }

    protected static function booted(): void
    {
        static::saving(function (LedgerCategory $category) {
            $category->class = $category->fundamentalType->normal_balance;
        });
    }
}
