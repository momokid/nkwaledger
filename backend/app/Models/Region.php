<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name'])]
class Region extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Region $region) {
            // cascades the soft delete down to districts, which in turn cascades to communities
            $region->districts()->each(fn(District $district) => $district->delete());

            // nulls out the reference on any farmer group pointing directly at this region
            \App\Models\FarmerGroup::where('region_id', $region->id)->update(['region_id' => null]);
        });
    }

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
