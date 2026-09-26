<?php

namespace Database\Factories;

use App\Models\KioskProduct;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    protected $model = OrderItem::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'kiosk_product_id' => KioskProduct::factory(),
            'quantity' => $this->faker->numberBetween(1, 5),
            'unit_price_at_order_time' => $this->faker->numberBetween(500, 50000),
        ];
    }
}
