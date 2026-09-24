<?php

namespace App\Support;

use App\Models\CatalogProduct;
use Illuminate\Support\Carbon;

// what the resolver found or made, plus whatever the scanned code carried
// beyond the product identity itself - batch and expiry belong to this
// supplier's specific stock, never to the shared catalog record
class ResolvedCatalogProduct
{
    public function __construct(
        public readonly CatalogProduct $catalogProduct,
        public readonly ?string $batch = null,
        public readonly ?Carbon $expiry = null,
    ) {}
}
