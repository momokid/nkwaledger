<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\District;
use Illuminate\Database\Eloquent\Factories\Factory;

class CommunityFactory extends Factory
{
    protected $model = Community::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'district_id' => District::factory(),
        ];
    }
}
