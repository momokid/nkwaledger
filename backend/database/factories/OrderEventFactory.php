<?php

namespace Database\Factories;

use App\Enums\OrderEventType;
use App\Models\Order;
use App\Models\OrderEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderEvent>
 */
class OrderEventFactory extends Factory
{
    protected $model = OrderEvent::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'event_type' => OrderEventType::Requested,
            'occurred_at' => now(),
        ];
    }
}
