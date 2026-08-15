<?php

namespace Database\Factories;

use App\Models\FarmerGroup;
use App\Models\FarmerGroupType;
use App\Models\Region;
use App\Models\District;
use App\Models\Community;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FarmerGroupFactory extends Factory
{
    protected $model = FarmerGroup::class;

    public function definition(): array
    {
        return [
            'name' => ucfirst($this->faker->unique()->words(3, true)),
            'group_type_id' => null,
            'region_id' => null,
            'district_id' => null,
            'community_id' => null,
            'description' => null,
            'is_shared_liability' => false,
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    public function withGroupType(?FarmerGroupType $type = null): static
    {
        return $this->state(fn() => [
            'group_type_id' => ($type ?? FarmerGroupType::factory()->create())->id,
        ]);
    }

    public function withLocation(?Region $region = null, ?District $district = null, ?Community $community = null): static
    {
        $region ??= Region::factory()->create();
        $district ??= District::factory()->create(['region_id' => $region->id]);
        $community ??= Community::factory()->create(['district_id' => $district->id]);

        return $this->state(fn() => [
            'region_id' => $region->id,
            'district_id' => $district->id,
            'community_id' => $community->id,
        ]);
    }
}
