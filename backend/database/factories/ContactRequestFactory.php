<?php

namespace Database\Factories;

use App\Enums\ContactRequestStatus;
use App\Models\ContactRequest;
use App\Models\ProduceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactRequest>
 */
class ContactRequestFactory extends Factory
{
    protected $model = ContactRequest::class;

    public function definition(): array
    {
        return [
            'contactable_type' => (new ProduceListing())->getMorphClass(),
            'contactable_id' => ProduceListing::factory()->create()->id,
            'requester_user_id' => User::factory(),
            'recipient_user_id' => User::factory(),
            'sender_phone' => '0244000000',
            'expires_at' => now()->addDays(7),
        ];
    }

    public function replied(): static
    {
        return $this->state(fn() => [
            'status' => ContactRequestStatus::Replied,
            'replied_at' => now(),
            'reply_message' => 'Sure, still available.',
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn() => [
            'status' => ContactRequestStatus::Expired,
            'expires_at' => now()->subDay(),
        ]);
    }
}
