<?php

namespace Database\Seeders;

use App\Models\FarmTypeCategory;
use App\Models\HealthCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class HealthCategorySeeder extends Seeder
{
    protected array $categories = [
        'Crop' => [
            'Leaf spots or marks',
            'Wilting or drying',
            'Yellowing leaves',
            'Insects or pest damage',
            'Fruit, grain or pod problems',
            'Root or stem rot',
            'Stunted growth',
            'Weeds',
            'Not sure',
        ],
        'Livestock' => [
            'Not eating or drinking',
            'Diarrhoea',
            'Coughing or breathing trouble',
            'Swelling, wounds or skin problems',
            'Lameness',
            'Fever or weakness',
            'Drop in eggs or milk',
            'Sudden deaths',
            'Not sure',
        ],
        'Aquatic' => [
            'Gasping at the surface',
            'Dead fish',
            'Spots or sores',
            'Not feeding',
            'Unusual swimming',
            'Water colour or smell problem',
            'Not sure',
        ],
    ];

    // the uuid is set here because DatabaseSeeder runs with model events off
    // a farm category that already has a list is left alone, so an admin's later edits survive a re-seed
    public function run(): void
    {
        foreach ($this->categories as $farmCategoryName => $names) {
            $farmCategory = FarmTypeCategory::where('name', $farmCategoryName)->first();

            if ($farmCategory === null || HealthCategory::where('farm_type_category_id', $farmCategory->id)->exists()) {
                continue;
            }

            foreach ($names as $position => $name) {
                HealthCategory::unguarded(fn() => HealthCategory::create(['uuid' => (string) Str::uuid7(), 'farm_type_category_id' => $farmCategory->id, 'name' => $name, 'sort_order' => $position + 1]));
            }
        }
    }
}
