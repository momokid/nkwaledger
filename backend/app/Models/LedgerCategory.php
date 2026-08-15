<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'class_id',
])]
class LedgerCategory extends Model
{
    use HasFactory, SoftDeletes;

    public function class(): BelongsTo
    {
        return $this->belongsTo(LedgerClass::class, 'class_id');
    }
}
