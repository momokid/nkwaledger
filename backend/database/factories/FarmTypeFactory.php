<?php

namespace Database\Factories;

use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmTypeFactory extends Factory
{
    protected $model = FarmType::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'category_id' => null,
            'is_active' => true,
        ];
    }

    public function withCategory(?FarmTypeCategory $category = null): static
    {
        return $this->state(fn() => [
            'category_id' => ($category ?? FarmTypeCategory::factory()->create())->id,
        ]);
    }
}
