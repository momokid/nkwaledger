<?php

namespace Database\Factories;

use App\Enums\StockSource;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmUnitStockFactory extends Factory
{
    protected $model = FarmUnitStock::class;

    public function definition(): array
    {
        $quantity = $this->faker->numberBetween(20, 300);

        return [
            'farm_unit_id' => FarmUnit::factory()->approved(),
            'source' => StockSource::Purchase,
            'opening_quantity' => $quantity,
            'current_quantity' => $quantity,
            'unit_of_measure' => 'birds',
            'acquisition_cost' => $quantity * 20,
            'started_on' => now()->subMonths(2),
            'ended_on' => null,
            'recorded_by' => User::factory(),
            'confirmed_at' => null,
            'confirmed_by' => null,
        ];
    }

    // checked by someone other than whoever wrote the number down
    public function confirmed(): static
    {
        return $this->state(fn() => [
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
        ]);
    }

    public function openingBalance(): static
    {
        return $this->state(fn() => ['source' => StockSource::OpeningBalance]);
    }

    public function closed(): static
    {
        return $this->state(fn() => ['ended_on' => now()]);
    }
}
