<?php

namespace Database\Factories;

use App\Enums\ProduceListingStatus;
use App\Models\FarmerProfile;
use App\Models\FarmUnitStock;
use App\Models\ProduceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProduceListingFactory extends Factory
{
    protected $model = ProduceListing::class;

    public function definition(): array
    {
        return [
            'farm_unit_stock_id' => FarmUnitStock::factory(),
            'farmer_profile_id' => FarmerProfile::factory(),
            'posted_by_user_id' => User::factory(),
            'status' => ProduceListingStatus::Active,
            'quantity_listed' => 10,
            'quantity_remaining' => 10,
            'farmer_agreed_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn() => ['status' => ProduceListingStatus::Draft, 'farmer_agreed_at' => null]);
    }

    public function withdrawn(): static
    {
        return $this->state(fn() => ['status' => ProduceListingStatus::Withdrawn]);
    }
}
