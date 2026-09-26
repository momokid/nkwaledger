<?php

namespace Database\Factories;

use App\Enums\OrderPaymentMethod;
use App\Models\FarmerProfile;
use App\Models\ProduceListing;
use App\Models\ProduceSale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProduceSale>
 */
class ProduceSaleFactory extends Factory
{
    protected $model = ProduceSale::class;

    public function definition(): array
    {
        return [
            'produce_listing_id' => ProduceListing::factory(),
            'buyer_user_id' => User::factory(),
            'farmer_profile_id' => FarmerProfile::factory(),
            'quantity' => 5,
            'payment_method' => OrderPaymentMethod::Bank,
            'amount_minor' => 10000,
            'requested_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn() => ['confirmed_at' => now()])->afterMaking(fn(ProduceSale $sale) => $sale->recomputeStatus());
    }

    public function received(): static
    {
        return $this->state(fn() => ['received_at' => now()])->afterMaking(fn(ProduceSale $sale) => $sale->recomputeStatus());
    }

    public function fullyConfirmed(): static
    {
        return $this->state(fn() => ['confirmed_at' => now(), 'received_at' => now()])
            ->afterMaking(fn(ProduceSale $sale) => $sale->recomputeStatus());
    }
}
