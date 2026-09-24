<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use Illuminate\Database\Seeder;

class ProductCategorySeeder extends Seeder
{
    // the starting list the spec itself suggests for the expiry-date open item (#12);
    // confirm against the regulator's list before Step 2 relies on this for real enforcement
    private const CATEGORIES = [
        'Fertilizer' => false,
        'Seed' => true,
        'Chemical' => true,
        'Feed' => true,
        'Veterinary Drugs' => true,
        'Equipment' => false,
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name => $requiresExpiryDate) {
            ProductCategory::firstOrCreate(['name' => $name], ['requires_expiry_date' => $requiresExpiryDate]);
        }
    }
}
