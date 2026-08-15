<?php

namespace Database\Factories;

use App\Models\FarmTypeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmTypeCategoryFactory extends Factory
{
    protected $model = FarmTypeCategory::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'is_active' => true,
        ];
    }
}
