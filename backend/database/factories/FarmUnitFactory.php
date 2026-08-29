<?php

namespace Database\Factories;

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmUnitFactory extends Factory
{
    protected $model = FarmUnit::class;

    public function definition(): array
    {
        return [
            'farmer_profile_id' => FarmerProfile::factory(),
            'farm_type_id' => FarmType::factory()->withCategory(),
            'community_id' => Community::factory(),
            'name' => 'Pen ' . $this->faker->unique()->numberBetween(1, 9999),
            'capacity' => $this->faker->numberBetween(20, 500),
            'capacity_unit' => 'birds',
            'created_by' => User::factory(),
            'approved_at' => null,
            'approved_by' => null,
            'is_active' => true,
        ];
    }

    // approved by someone other than whoever set the unit up
    public function approved(): static
    {
        return $this->state(fn() => [
            'approved_at' => now(),
            'approved_by' => User::factory(),
        ]);
    }

    public function forFarmer(FarmerProfile $profile): static
    {
        return $this->state(fn() => [
            'farmer_profile_id' => $profile->id,
            'community_id' => $profile->community_id,
        ]);
    }
}
