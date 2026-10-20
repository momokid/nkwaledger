<?php

namespace Database\Factories;

use App\Models\FarmTypeCategory;
use App\Models\HealthCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HealthCategory>
 */
class HealthCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'farm_type_category_id' => FarmTypeCategory::factory(),
            'name' => fake()->unique()->words(3, true),
            'sort_order' => fake()->numberBetween(1, 20),
        ];
    }
}
