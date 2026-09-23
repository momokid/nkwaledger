<?php

namespace Database\Factories;

use App\Models\CatalogProduct;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogProduct>
 */
class CatalogProductFactory extends Factory
{
    protected $model = CatalogProduct::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'category_id' => ProductCategory::factory(),
            'unit_id' => ProductUnit::factory(),
        ];
    }

    public function withBarcode(): static
    {
        return $this->state(fn() => [
            'barcode' => $this->faker->unique()->numerify('###############'),
            'barcode_type' => \App\Enums\BarcodeType::Barcode,
        ]);
    }

    public function seeded(): static
    {
        return $this->state(fn() => ['seeded' => true]);
    }
}
