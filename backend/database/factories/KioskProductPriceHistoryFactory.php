<?php

namespace Database\Factories;

use App\Enums\PriceHistoryType;
use App\Models\KioskProduct;
use App\Models\KioskProductPriceHistory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KioskProductPriceHistory>
 */
class KioskProductPriceHistoryFactory extends Factory
{
    protected $model = KioskProductPriceHistory::class;

    public function definition(): array
    {
        return [
            'kiosk_product_id' => KioskProduct::factory(),
            'old_price' => null,
            'new_price' => $this->faker->numberBetween(500, 50000),
            'type' => PriceHistoryType::Changed,
        ];
    }
}
