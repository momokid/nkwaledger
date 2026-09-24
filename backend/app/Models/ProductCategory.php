<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'requires_expiry_date', 'is_active'])]
class ProductCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $attributes = [
        'requires_expiry_date' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'requires_expiry_date' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
