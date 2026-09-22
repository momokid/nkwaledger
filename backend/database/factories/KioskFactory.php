<?php

namespace Database\Factories;

use App\Models\District;
use App\Models\Kiosk;
use App\Models\Region;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kiosk>
 */
class KioskFactory extends Factory
{
    protected $model = Kiosk::class;

    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'name' => substr($this->faker->unique()->company(), 0, 60),
            'region_id' => Region::factory(),
            'district_id' => District::factory(),
            'contact_phone' => '024' . $this->faker->unique()->numerify('#######'),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn() => [
            'confirmed_at' => now(),
            'status' => \App\Enums\KioskStatus::Active,
        ]);
    }
}
