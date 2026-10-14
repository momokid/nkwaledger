<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['farm_unit_id', 'path'])]
class FarmUnitImage extends Model
{
    public function farmUnit(): BelongsTo
    {
        return $this->belongsTo(FarmUnit::class);
    }
}
