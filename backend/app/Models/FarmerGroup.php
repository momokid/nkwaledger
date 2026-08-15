<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'name',
    'group_type_id',
    'region_id',
    'district_id',
    'community_id',
    'description',
    'is_shared_liability',
    'is_active',
    'created_by',
])]
class FarmerGroup extends Model
{
    use HasFactory, SoftDeletes;

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function groupType(): BelongsTo
    {
        return $this->belongsTo(FarmerGroupType::class, 'group_type_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function community(): BelongsTo
    {
        return $this->belongsTo(Community::class);
    }
}