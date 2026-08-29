<?php

namespace Database\Factories;

use App\Enums\IdentityType;
use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\User;
use App\Support\IdentityDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmerProfileFactory extends Factory
{
    protected $model = FarmerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'date_of_birth' => $this->faker->dateTimeBetween('-60 years', '-18 years'),
            'community_id' => Community::factory(),
            'farmer_group_id' => null,
            'identity_type' => null,
            'identity_number_hash' => null,
            'identity_verified_at' => null,
            'identity_verified_by' => null,
            'registered_by' => null,
            'onboarded_at' => null,
            'opening_balance_posted_at' => null,
            'is_active' => true,
        ];
    }

    public function withIdentity(IdentityType $type = IdentityType::GhanaCard, ?string $number = null): static
    {
        return $this->state(fn() => [
            'identity_type' => $type,
            'identity_number_hash' => IdentityDocument::hash($number ?? $this->faker->unique()->numerify('GHA-#########-#')),
        ]);
    }

    public function verified(): static
    {
        return $this->withIdentity()->state(fn() => [
            'identity_verified_at' => now(),
            'identity_verified_by' => User::factory(),
        ]);
    }

    public function onboarded(): static
    {
        return $this->state(fn() => ['onboarded_at' => now()]);
    }
}
