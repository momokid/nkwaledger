<?php

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

    $this->vet = User::factory()->create();
    $this->vet->assignRole('vet');

    $farmCategory = FarmTypeCategory::create(['name' => 'Livestock']);
    $this->weak = HealthCategory::factory()->create(['farm_type_category_id' => $farmCategory->id, 'name' => 'Weak or not eating', 'sort_order' => 1]);
    $this->other = HealthCategory::factory()->create(['farm_type_category_id' => $farmCategory->id, 'name' => 'Not sure', 'sort_order' => 2]);
    $this->retired = HealthCategory::factory()->create(['farm_type_category_id' => $farmCategory->id, 'name' => 'Old', 'is_active' => false]);
    $this->foreign = HealthCategory::factory()->create(['farm_type_category_id' => FarmTypeCategory::create(['name' => 'Crops'])->id]);

    $unit = FarmUnit::factory()->create(['farm_type_id' => FarmType::factory()->withCategory($farmCategory)->create()->id]);

    $this->makeReport = fn(array $extra = []) => DiseaseReport::factory()->create($extra + [
        'farm_unit_id' => $unit->id,
        'farmer_profile_id' => $unit->farmer_profile_id,
        'assigned_officer_id' => $this->vet->id,
    ]);
    $this->ready = fn() => ($this->makeReport)([
        'ai_status' => 'ready', 'health_category_id' => $this->weak->id,
        'ai_confidence' => 0.82, 'ai_urgent' => false, 'ai_note' => 'Birds look weak.',
    ]);
});

describe('draft on the officer page', function () {
    it('shows the draft only when ready, with the active categories', function () {
        $report = ($this->ready)();

        $this->actingAs($this->vet)->get("/vet/reports/{$report->uuid}")
            ->assertInertia(fn($page) => $page
                ->where('report.ai.category_uuid', $this->weak->uuid)
                ->where('report.ai.category', 'Weak or not eating')
                ->where('report.ai.note', 'Birds look weak.')
                ->where('report.ai.urgent', false)
                ->has('categories', 2));
    });

    it('hides a low confidence category but still shows urgent', function () {
        $report = ($this->makeReport)([
            'ai_status' => 'low_confidence', 'health_category_id' => $this->weak->id,
            'ai_confidence' => 0.4, 'ai_urgent' => true, 'ai_note' => 'secret',
        ]);

        $this->actingAs($this->vet)->get("/vet/reports/{$report->uuid}")
            ->assertInertia(fn($page) => $page
                ->where('report.ai.category_uuid', null)
                ->where('report.ai.note', null)
                ->where('report.ai.urgent', true));
    });

    it('sends no id and nothing to the farmer', function () {
        $report = ($this->ready)();

        $this->actingAs($this->vet)->get("/vet/reports/{$report->uuid}")
            ->assertInertia(fn($page) => $page->missing('report.id')->missing('report.health_category_id'));

        $farmer = User::factory()->create();
        $farmer->assignRole('farmer');
        $profile = FarmerProfile::factory()->create(['user_id' => $farmer->id]);
        $mine = DiseaseReport::factory()->create(['farm_unit_id' => FarmUnit::factory()->create(['farmer_profile_id' => $profile->id])->id, 'farmer_profile_id' => $profile->id, 'ai_status' => 'ready', 'ai_note' => 'hidden']);

        $this->actingAs($farmer)->get("/my-farm/reports/{$mine->uuid}")
            ->assertOk()->assertDontSee('hidden')->assertDontSee('ai_note');
    });
});

describe('saving the category', function () {
    it('stores agreed when the officer keeps the draft', function () {
        $report = ($this->ready)();

        $this->actingAs($this->vet)
            ->post("/vet/reports/{$report->uuid}/category", ['health_category' => $this->weak->uuid])
            ->assertRedirect();

        $report->refresh();
        expect($report->officer_health_category_id)->toBe($this->weak->id)
            ->and($report->officer_verdict)->toBe('agreed')
            ->and($report->officer_reviewed_at)->not->toBeNull();
    });

    it('stores edited when the officer picks another', function () {
        $report = ($this->ready)();

        $this->actingAs($this->vet)->post("/vet/reports/{$report->uuid}/category", ['health_category' => $this->other->uuid]);

        expect($report->fresh()->officer_verdict)->toBe('edited')
            ->and($report->fresh()->officer_health_category_id)->toBe($this->other->id);
    });

    it('stores no verdict when there was no shown draft', function () {
        $report = ($this->makeReport)();

        $this->actingAs($this->vet)->post("/vet/reports/{$report->uuid}/category", ['health_category' => $this->other->uuid]);

        expect($report->fresh()->officer_verdict)->toBeNull()
            ->and($report->fresh()->officer_health_category_id)->toBe($this->other->id);
    });

    it('refuses an inactive or other-kind category', function () {
        $report = ($this->ready)();

        foreach ([$this->retired, $this->foreign] as $bad) {
            $this->actingAs($this->vet)->post("/vet/reports/{$report->uuid}/category", ['health_category' => $bad->uuid])
                ->assertSessionHasErrors('health_category');
        }
        expect($report->fresh()->officer_health_category_id)->toBeNull();
    });

    it('turns away an officer the report is not assigned to', function () {
        $report = ($this->ready)();
        $stranger = User::factory()->create();
        $stranger->assignRole('vet');

        $this->actingAs($stranger)->post("/vet/reports/{$report->uuid}/category", ['health_category' => $this->weak->uuid])->assertNotFound();
    });
});
