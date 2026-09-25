<?php

namespace Database\Factories;

use App\Models\Commission;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commission>
 */
class CommissionFactory extends Factory
{
    protected $model = Commission::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'agent_id' => User::factory(),
        ];
    }
}
