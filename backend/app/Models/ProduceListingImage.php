<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// mirrors KioskProductImage exactly - same shape, same conventions
#[Fillable(['produce_listing_id', 'path'])]
class ProduceListingImage extends Model
{
    public function produceListing(): BelongsTo
    {
        return $this->belongsTo(ProduceListing::class);
    }
}
