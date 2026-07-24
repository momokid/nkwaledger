<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

#[Fillable([
    'name',
    'group_type',
    'region',
    'district',
    'community',
    'description',
    'is_shared_liability',
    'is_active',
    'created_by',
])]
class FarmerGroup extends Model
{
    use HasFactory, SoftDeletes;

    public const GROUP_TYPES = ['cooperative', 'vsla', 'outgrower', 'other'];

    protected $attributes = [
        'is_shared_liability' => false,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_shared_liability' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (FarmerGroup $group) {
            if (! in_array($group->group_type, self::GROUP_TYPES, true)) {
                throw new InvalidArgumentException(
                    'Farmer group type must be one of: ' . implode(', ', self::GROUP_TYPES)
                );
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
