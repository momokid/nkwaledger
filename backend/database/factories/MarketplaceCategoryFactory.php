<?php

namespace Database\Factories;

use App\Models\MarketplaceCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MarketplaceCategory>
 */
class MarketplaceCategoryFactory extends Factory
{
    protected $model = MarketplaceCategory::class;

    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'display_count' => 8,
            'sort_order' => $this->faker->numberBetween(0, 100),
        ];
    }
}
