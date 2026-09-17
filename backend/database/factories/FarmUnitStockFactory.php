<?php

namespace Database\Factories;

use App\Enums\MovementReason;
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
            'source' => StockSource::OpeningBalance,
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

    // most tests just need an established batch to build other behaviour on top of, not
    // to exercise the confirmation policy itself - so an opening-balance batch (the
    // default source) is confirmed straight after creation, same as the old default.
    // a test that explicitly asks for a Purchase-sourced batch is almost always
    // deliberately exercising the held-back-until-checked behaviour, so that one is
    // left alone here; use ->pendingOpening() to hold back an opening-balance batch too
    public function configure(): static
    {
        return $this->afterCreating(function (FarmUnitStock $stock) {
            if ($stock->source !== StockSource::OpeningBalance) {
                return;
            }

            $stock->movements()->where('reason', MovementReason::Opening)->update([
                'confirmed_at' => now(),
                'confirmed_by' => $stock->recorded_by,
            ]);

            $stock->refreshCount();
        });
    }

    // checked by someone other than whoever wrote the number down
    public function confirmed(): static
    {
        return $this->state(fn() => [
            'confirmed_at' => now(),
            'confirmed_by' => User::factory(),
        ]);
    }

    // opt-in for a test that specifically wants an opening count nobody has checked yet -
    // reverses configure()'s usual convenience
    public function pendingOpening(): static
    {
        return $this->afterCreating(function (FarmUnitStock $stock) {
            $stock->movements()->where('reason', MovementReason::Opening)->update([
                'confirmed_at' => null,
                'confirmed_by' => null,
            ]);

            $stock->refreshCount();
        });
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
