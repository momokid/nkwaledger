<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class FarmerGroupType extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (FarmerGroupType $type) {
            $type->farmerGroups()->update(['group_type_id' => null]);
        });
    }

    public function farmerGroups(): HasMany
    {
        return $this->hasMany(FarmerGroup::class, 'group_type_id');
    }
}
