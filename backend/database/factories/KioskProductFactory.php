<?php

namespace Database\Factories;

use App\Models\CatalogProduct;
use App\Models\Kiosk;
use App\Models\KioskProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KioskProduct>
 */
class KioskProductFactory extends Factory
{
    protected $model = KioskProduct::class;

    public function definition(): array
    {
        return [
            'kiosk_id' => Kiosk::factory(),
            'catalog_product_id' => CatalogProduct::factory(),
            'price' => $this->faker->numberBetween(500, 50000),
        ];
    }

    public function priceConfirmed(): static
    {
        return $this->state(fn() => ['price_confirmed_at' => now()]);
    }

    public function stalePrice(int $days = 20): static
    {
        return $this->state(fn() => ['price_confirmed_at' => now()->subDays($days)]);
    }

    public function expired(): static
    {
        return $this->state(fn() => ['expiry_date' => now()->subDay()->toDateString()]);
    }
}
