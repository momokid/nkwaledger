<?php

namespace Database\Factories;

use App\Enums\KioskReportStatus;
use App\Models\FarmerProfile;
use App\Models\Kiosk;
use App\Models\KioskReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KioskReport>
 */
class KioskReportFactory extends Factory
{
    protected $model = KioskReport::class;

    public function definition(): array
    {
        return [
            'kiosk_id' => Kiosk::factory(),
            'farmer_profile_id' => FarmerProfile::factory(),
            'reason' => $this->faker->randomElement(['wrong_price', 'no_stock', 'rude', 'other']),
            'details' => $this->faker->sentence(),
            'status' => KioskReportStatus::Open,
            'supplier_due_at' => now()->addDays(3),
        ];
    }

    public function withAdmin(): static
    {
        return $this->state(fn() => [
            'status' => KioskReportStatus::WithAdmin,
            'admin_due_at' => now()->addDays(15),
        ]);
    }
}
