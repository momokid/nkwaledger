<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'surname'            => fake()->lastName(),
            'first_name'         => fake()->firstName(),
            'other_name'         => null,
            'phone'              => '+233' . fake()->unique()->numerify('2########'),
            'email'              => null,
            'phone_verified_at'  => now(),
            'email_verified_at'  => null,
            'password'           => static::$password ??= Hash::make('Password@123'),
            'is_active'          => true,
            'remember_token'     => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'phone_verified_at' => null,
        ]);
    }

    public function withEmail(): static
    {
        return $this->state(fn (array $attributes) => [
            'email'              => fake()->unique()->safeEmail(),
            'email_verified_at'  => now(),
        ]);
    }

    public function oauthUser(): static
    {
        return $this->state(fn (array $attributes) => [
            'password' => null,
        ]);
    }
}
