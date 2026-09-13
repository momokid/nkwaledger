<?php

namespace Database\Factories;

use App\Enums\OfficerRole;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiseaseReport>
 */
class DiseaseReportFactory extends Factory
{
    protected $model = DiseaseReport::class;

    public function definition(): array
    {
        return [
            'farm_unit_id' => FarmUnit::factory(),
            'farmer_profile_id' => FarmerProfile::factory(),
            'reported_by' => null,
            'category' => 'Livestock',
            'routed_role' => OfficerRole::Vet,
            'assigned_officer_id' => null,
            'status' => 'new',
            'photo_path' => 'disease-reports/' . $this->faker->uuid() . '.jpg',
            'description' => $this->faker->sentence(),
        ];
    }

    public function assignedTo(int $officerId): static
    {
        return $this->state(fn() => ['assigned_officer_id' => $officerId]);
    }
}
