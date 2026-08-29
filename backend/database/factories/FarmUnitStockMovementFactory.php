<?php

namespace Database\Factories;

use App\Enums\MovementReason;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmUnitStockMovementFactory extends Factory
{
    protected $model = FarmUnitStockMovement::class;

    public function definition(): array
    {
        return [
            'farm_unit_stock_id' => FarmUnitStock::factory(),
            'reason' => MovementReason::Birth,
            'quantity' => $this->faker->numberBetween(1, 10),
            'is_increase' => true,
            'occurred_on' => now()->subDays(7),
            'note' => null,
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
}
