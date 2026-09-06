<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

#[Fillable([
    'name',
    'category_id',
    'quantity_is_decimal',
    'is_active',
])]
class FarmType extends Model
{
    use HasFactory, SoftDeletes;
    use Auditable;
    protected $attributes = [
        'quantity_is_decimal' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'quantity_is_decimal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FarmTypeCategory::class, 'category_id');
    }
}
