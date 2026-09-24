<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['kiosk_product_id', 'path'])]
class KioskProductImage extends Model
{
    public function kioskProduct(): BelongsTo
    {
        return $this->belongsTo(KioskProduct::class);
    }
}
