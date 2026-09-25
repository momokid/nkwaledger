<?php

namespace Database\Factories;

use App\Enums\OrderPaymentMethod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\Kiosk;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'kiosk_id' => Kiosk::factory(),
            'farmer_profile_id' => FarmerProfile::factory(),
            'farm_unit_id' => FarmUnit::factory(),
            'payment_method' => OrderPaymentMethod::Bank,
            'requested_at' => now(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn() => ['confirmed_at' => now()])->afterMaking(fn(Order $order) => $order->recomputeStatus());
    }

    public function received(): static
    {
        return $this->state(fn() => ['received_at' => now()])->afterMaking(fn(Order $order) => $order->recomputeStatus());
    }

    public function fullyConfirmed(): static
    {
        return $this->state(fn() => ['confirmed_at' => now(), 'received_at' => now()])
            ->afterMaking(fn(Order $order) => $order->recomputeStatus());
    }

    public function closed(): static
    {
        return $this->state(fn() => ['closed_at' => now()])->afterMaking(fn(Order $order) => $order->recomputeStatus());
    }
}
