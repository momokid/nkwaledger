<?php

namespace Database\Seeders;

use App\Models\MarketplaceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MarketplaceCategorySeeder extends Seeder
{
    // a starting set admin can add to, edit, or remove from at any time
    private const NAMES = [
        'Farm Produce',
        'Farm Inputs',
        'Kiosk',
        'Seasonal Farm Produce',
        'Farmer Produce Type',
    ];

    public function run(): void
    {
        foreach (self::NAMES as $index => $name) {
            MarketplaceCategory::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'display_count' => 8, 'sort_order' => $index],
            );
        }
    }
}
