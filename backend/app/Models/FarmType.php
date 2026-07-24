<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'name',
    'category',
    'is_active',
])]
class FarmType extends Model
{
    use HasFactory, SoftDeletes;

    public const CATEGORIES = ['crop', 'livestock'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FarmType $farmType) {
            if (! in_array($farmType->category, self::CATEGORIES, true)) {
                throw new InvalidArgumentException(
                    'Farm type category must be one of: ' . implode(', ', self::CATEGORIES)
                );
            }
        });
    }
}
