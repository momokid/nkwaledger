<?php

use App\Jobs\ReviewDiseaseReport;
use App\Models\DiseaseReport;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\HealthCategory;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    config(['services.openrouter.key' => 'test-key', 'services.openrouter.model' => 'qwen/test-model']);

    $category = FarmTypeCategory::create(['name' => 'Livestock']);
    $this->wilting = HealthCategory::factory()->create(['farm_type_category_id' => $category->id, 'name' => 'Weak or not eating', 'sort_order' => 1]);
    HealthCategory::factory()->create(['farm_type_category_id' => $category->id, 'name' => 'Not sure', 'sort_order' => 2]);

    $farmType = FarmType::factory()->withCategory($category)->create();
    $unit = FarmUnit::factory()->create(['farm_type_id' => $farmType->id]);

    $path = 'disease-reports/p.jpg';
    $image = UploadedFile::fake()->image('p.jpg', 50, 50);
    Storage::disk('local')->put($path, file_get_contents($image->getRealPath()));

    $this->report = DiseaseReport::factory()->create([
        'farm_unit_id' => $unit->id,
        'farmer_profile_id' => $unit->farmer_profile_id,
        'photo_path' => $path,
        'media_disk' => 'local',
    ]);

    $this->good = ['photo_usable' => true, 'category' => 'Weak or not eating', 'confidence' => 0.82, 'urgent' => false, 'note' => 'Birds look weak.'];
    $this->reply = fn(array $content, int $status = 200) => Http::fake([
        'openrouter.ai/*' => Http::response(['choices' => [['message' => ['content' => json_encode($content)]]]], $status),
    ]);
    $this->run = fn() => ReviewDiseaseReport::dispatchSync($this->report->id);
});

it('saves a confident review as ready', function () {
    ($this->reply)($this->good);
    ($this->run)();

    $report = $this->report->fresh();

    expect($report->ai_status)->toBe('ready')
        ->and($report->health_category_id)->toBe($this->wilting->id)
        ->and((float) $report->ai_confidence)->toBe(0.82)
        ->and($report->ai_urgent)->toBeFalse()
        ->and($report->ai_note)->toBe('Birds look weak.')
        ->and($report->ai_model)->toBe('qwen/test-model')
        ->and($report->ai_reviewed_at)->not->toBeNull();
});

it('marks 70% and above ready and below 70% low confidence', function (float $confidence, string $status) {
    ($this->reply)([...$this->good, 'confidence' => $confidence]);
    ($this->run)();

    expect($this->report->fresh()->ai_status)->toBe($status);
})->with([[0.70, 'ready'], [0.69, 'low_confidence']]);

it('lets the 70% line be changed in config', function () {
    config(['services.openrouter.min_confidence' => 0.9]);
    ($this->reply)($this->good);
    ($this->run)();

    expect($this->report->fresh()->ai_status)->toBe('low_confidence');
});

it('keeps the urgent flag whatever the confidence', function () {
    ($this->reply)([...$this->good, 'confidence' => 0.2, 'urgent' => true]);
    ($this->run)();

    $report = $this->report->fresh();

    expect($report->ai_status)->toBe('low_confidence')->and($report->ai_urgent)->toBeTrue();
});

it('marks an unusable photo unusable, with no category', function () {
    ($this->reply)(['photo_usable' => false, 'category' => '', 'confidence' => 0, 'urgent' => true, 'note' => 'Too dark.']);
    ($this->run)();

    $report = $this->report->fresh();

    expect($report->ai_status)->toBe('unusable')
        ->and($report->health_category_id)->toBeNull()
        ->and($report->ai_urgent)->toBeTrue();
});

it('marks the review failed when the reviewer gives nothing', function () {
    Http::fake(['openrouter.ai/*' => Http::response([], 500)]);
    ($this->run)();

    expect($this->report->fresh()->ai_status)->toBe('failed');
});

it('marks the review failed when the photo cannot be read', function () {
    Storage::disk('local')->delete($this->report->photo_path);
    Http::fake();
    ($this->run)();

    expect($this->report->fresh()->ai_status)->toBe('failed');
    Http::assertNothingSent();
});

it('does nothing and calls nobody when no key is set', function () {
    config(['services.openrouter.key' => null]);
    Http::fake();
    ($this->run)();

    expect($this->report->fresh()->ai_status)->toBeNull();
    Http::assertNothingSent();
});

it('sends the farm type\'s active categories only', function () {
    HealthCategory::factory()->create(['farm_type_category_id' => $this->wilting->farm_type_category_id, 'name' => 'Retired', 'is_active' => false]);
    HealthCategory::factory()->create(['name' => 'Other farm type']);
    ($this->reply)($this->good);
    ($this->run)();

    Http::assertSent(fn($request) => $request->data()['response_format']['json_schema']['schema']['properties']['category']['enum'] === ['Weak or not eating', 'Not sure']);
});

it('reviews a report that is still hidden because it is waiting for its photo', function () {
    $this->report->forceFill(['status' => 'waiting_for_photo'])->save();
    ($this->reply)($this->good);
    ($this->run)();

    expect(DiseaseReport::withoutGlobalScopes()->find($this->report->id)->ai_status)->toBe('ready');
});

it('never shows the farmer any AI field', function () {
    ($this->reply)($this->good);
    ($this->run)();

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $farmerUser = $this->report->farmerProfile->user;
    $farmerUser->assignRole('farmer');

    $body = $this->actingAs($farmerUser)->get("/my-farm/reports/{$this->report->uuid}")->assertOk()->getContent();

    expect($body)->not->toContain('ai_')->not->toContain('Birds look weak.')->not->toContain('0.82');
});
