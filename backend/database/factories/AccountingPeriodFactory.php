<?php

namespace Database\Factories;

use App\Models\AccountingPeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AccountingPeriod> */
class AccountingPeriodFactory extends Factory
{
    protected $model = AccountingPeriod::class;

    public function definition(): array
    {
        // each period lands in its own month, so two of them never overlap by accident
        static $offset = 0;

        $start = now()->startOfMonth()->addMonths($offset++);

        return [
            'name'      => $start->format('F Y') . ' ' . fake()->unique()->numberBetween(1000, 9999),
            'starts_on' => $start->toDateString(),
            'ends_on'   => $start->copy()->endOfMonth()->toDateString(),
            'status'    => AccountingPeriod::OPEN,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn() => [
            'status'    => AccountingPeriod::CLOSED,
            'closed_at' => now(),
        ]);
    }
}
