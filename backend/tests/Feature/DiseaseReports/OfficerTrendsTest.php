<?php

use App\Models\Community;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\HealthCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->adviser = User::factory()->create();
    $this->adviser->assignRole('adviser');
    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');

    $category = FarmTypeCategory::create(['name' => 'Crop']);
    $type = FarmType::create(['name' => 'Maize', 'category_id' => $category->id]);
    $this->blight = HealthCategory::factory()->create(['farm_type_category_id' => $category->id, 'name' => 'Blight']);
    $this->rust = HealthCategory::factory()->create(['farm_type_category_id' => $category->id, 'name' => 'Rust']);

    $user = User::factory()->create();
    $user->assignRole('farmer');
    $profile = FarmerProfile::factory()->create(['user_id' => $user->id]);
    $this->kumasi = Community::factory()->create(['name' => 'Kumasi']);
    $this->tamale = Community::factory()->create(['name' => 'Tamale']);

    $this->report = function (array $extra = [], ?Community $community = null) use ($type, $profile) {
        $unit = FarmUnit::factory()->approved()->create([
            'farmer_profile_id' => $profile->id,
            'farm_type_id' => $type->id,
            'community_id' => ($community ?? $this->kumasi)->id,
        ]);

        return DiseaseReport::factory()->create($extra + [
            'farm_unit_id' => $unit->id,
            'farmer_profile_id' => $profile->id,
            'assigned_officer_id' => $this->adviser->id,
        ]);
    };
});

it('counts the officer\'s own reports for 90 days and for 30 days', function () {
    ($this->report)();
    ($this->report)(['created_at' => now()->subDays(45)]);
    ($this->report)(['created_at' => now()->subDays(120)]);
    ($this->report)(['assigned_officer_id' => $this->vet->id]);

    $this->actingAs($this->adviser)
        ->get('/adviser/trends')
        ->assertOk()
        ->assertInertia(fn($page) => $page
            ->component('Officer/Trends')
            ->where('totals', ['ninety' => 2, 'thirty' => 1])
            ->where('field', 'crops')
            ->where('basePath', '/adviser'));
});

it('counts the officer\'s category first, then the AI one when it was shown, else not categorised', function () {
    ($this->report)(['officer_health_category_id' => $this->rust->id, 'health_category_id' => $this->blight->id, 'ai_status' => 'ready']);
    ($this->report)(['health_category_id' => $this->blight->id, 'ai_status' => 'ready']);
    ($this->report)(['health_category_id' => $this->blight->id, 'ai_status' => 'failed']);
    ($this->report)();

    $this->actingAs($this->adviser)
        ->get('/adviser/trends')
        ->assertInertia(fn($page) => $page
            ->where('problems', [
                ['uuid' => $this->blight->uuid, 'name' => 'Blight', 'count' => 1],
                ['uuid' => $this->rust->uuid, 'name' => 'Rust', 'count' => 1],
                ['uuid' => 'none', 'name' => 'Not yet categorised', 'count' => 2],
            ]));
});

it('gives one count per week that adds up to the 90 day total, and counts by community', function () {
    ($this->report)();
    ($this->report)(['created_at' => now()->subDays(20)], $this->tamale);
    ($this->report)(['created_at' => now()->subDays(21)], $this->tamale);

    $this->actingAs($this->adviser)
        ->get('/adviser/trends')
        ->assertInertia(fn($page) => $page
            ->where('weeks', fn($weeks) => collect($weeks)->sum('count') === 3 && collect($weeks)->every(fn($w) => isset($w['week_start'])))
            ->where('communities', [
                ['name' => 'Tamale', 'count' => 2],
                ['name' => 'Kumasi', 'count' => 1],
            ]));
});

it('works for a vet at their own address and keeps the roles apart', function () {
    $this->actingAs($this->vet)->get('/vet/trends')
        ->assertOk()
        ->assertInertia(fn($page) => $page->where('field', 'livestock')->where('basePath', '/vet'));

    $this->actingAs($this->vet)->get('/adviser/trends')->assertForbidden();
    $this->actingAs($this->adviser)->get('/vet/trends')->assertForbidden();
});

it('filters the dashboard list by a category from the trends page', function () {
    $blight = ($this->report)(['health_category_id' => $this->blight->id, 'ai_status' => 'ready']);
    ($this->report)(['officer_health_category_id' => $this->rust->id]);
    $plain = ($this->report)();

    $this->actingAs($this->adviser)->get("/adviser/dashboard?category={$this->blight->uuid}")
        ->assertInertia(fn($page) => $page
            ->has('reports', 1)
            ->where('reports.0.uuid', $blight->uuid)
            ->where('categoryName', 'Blight'));

    $this->actingAs($this->adviser)->get('/adviser/dashboard?category=none')
        ->assertInertia(fn($page) => $page->has('reports', 1)->where('reports.0.uuid', $plain->uuid));
});
