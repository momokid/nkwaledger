<?php

namespace Database\Factories;

use App\Enums\SupplierAccountStatus;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'business_name' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
        ];
    }

    // email verified, and the underlying user's phone marked verified too - both legs the cap counts by
    public function verified(): static
    {
        return $this->state(fn() => ['email_verified_at' => now()])
            ->afterCreating(function (Supplier $supplier) {
                $supplier->user->update(['phone_verified_at' => now()]);
            });
    }

    public function suspended(): static
    {
        return $this->state(fn() => [
            'account_status' => SupplierAccountStatus::Suspended,
            'suspended_at' => now(),
            'suspension_reason' => 'Repeated unanswered reports',
        ]);
    }
}
