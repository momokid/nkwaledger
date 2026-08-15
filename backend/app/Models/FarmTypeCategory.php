<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'is_active',
])]
class FarmTypeCategory extends Model
{
    use HasFactory, SoftDeletes;

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
        static::deleting(function (FarmTypeCategory $category) {
            // the category_id foreign key's nullOnDelete only fires on a real SQL DELETE,
            // but this is a soft delete, so we clear the reference explicitly here
            $category->farmTypes()->update(['category_id' => null]);
        });
    }

    public function farmTypes(): HasMany
    {
        return $this->hasMany(FarmType::class, 'category_id');
    }
}
