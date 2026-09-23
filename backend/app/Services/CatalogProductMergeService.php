<?php

namespace App\Services;

use App\Models\CatalogProduct;
use App\Models\KioskProduct;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CatalogProductMergeService
{
    // folds a duplicate catalog record into the one it was a copy of; the duplicate
    // stays in the table, pointed at the keeper, never physically removed
    public function merge(CatalogProduct $duplicate, CatalogProduct $keeper): void
    {
        if ($duplicate->is($keeper)) {
            throw new InvalidArgumentException('A catalog product cannot be merged into itself.');
        }

        DB::transaction(function () use ($duplicate, $keeper) {
            KioskProduct::where('catalog_product_id', $duplicate->id)
                ->get()
                ->each(function (KioskProduct $listing) use ($keeper) {
                    $alreadyListed = KioskProduct::where('kiosk_id', $listing->kiosk_id)
                        ->where('catalog_product_id', $keeper->id)
                        ->exists();

                    // the kiosk already lists the keeper - reassigning would violate the
                    // one-listing-per-kiosk rule, so the duplicate's own row simply goes
                    if ($alreadyListed) {
                        $listing->delete();

                        return;
                    }

                    $listing->update(['catalog_product_id' => $keeper->id]);
                });

            $duplicate->update(['merged_into_id' => $keeper->id]);
            $duplicate->delete();
        });
    }
}
