<?php

namespace Database\Seeders;

use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use Illuminate\Database\Seeder;

class FarmTypeSeeder extends Seeder
{
    protected array $types = [
        'Crop' => [
            'Maize',
            'Rice',
            'Cassava',
            'Yam',
            'Plantain',
            'Cocoa',
            'Oil Palm',
            'Cashew',
            'Groundnut',
            'Cowpea',
            'Soya Bean',
            'Tomato',
            'Pepper',
            'Onion',
            'Citrus',
        ],
        'Livestock' => [
            'Cattle',
            'Sheep',
            'Goat',
            'Pig',
            'Layers',
            'Broilers',
            'Guinea Fowl',
            'Rabbit',
            'Snail',
            'Bees',
        ],
        'Aquatic' => [
            'Tilapia',
            'Catfish',
        ],
    ];

    public function run(): void
    {
        foreach ($this->types as $categoryName => $names) {
            $category = FarmTypeCategory::firstOrCreate(['name' => $categoryName]);

            foreach ($names as $name) {
                FarmType::updateOrCreate(
                    ['name' => $name],
                    ['category_id' => $category->id]
                );
            }
        }
    }
}
