<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'district_id'])]
class Community extends Model
{
    use HasFactory, SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (Community $community) {
            \App\Models\FarmerGroup::where('community_id', $community->id)->update(['community_id' => null]);
        });
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }
}
