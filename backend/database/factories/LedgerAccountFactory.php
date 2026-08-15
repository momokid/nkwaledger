<?php

namespace Database\Factories;

use App\Models\LedgerAccount;
use App\Models\LedgerAccountType;
use Illuminate\Database\Eloquent\Factories\Factory;

class LedgerAccountFactory extends Factory
{
    protected $model = LedgerAccount::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(2, true)),
            'type_id' => null,
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function withType(?LedgerAccountType $type = null): static
    {
        return $this->state(fn() => [
            'type_id' => ($type ?? LedgerAccountType::factory()->create())->id,
        ]);
    }

    public function system(): static
    {
        return $this->state(fn() => ['is_system' => true]);
    }
}
