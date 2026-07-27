<?php

namespace Database\Factories;

use App\Models\LedgerAccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerAccountTypeFactory extends Factory
{
    protected $model = LedgerAccountType::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->word()),
            'normal_balance' => $this->faker->randomElement(['debit', 'credit']),
        ];
    }
}
