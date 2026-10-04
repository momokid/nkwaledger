<?php

use App\Models\Community;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmUnit;
use App\Models\FarmUnitImage;
use App\Models\User;
use App\Services\ApprovalQueueService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->community = Community::factory()->create();
    $this->farmType = FarmType::factory()->withCategory()->create();
});

function photoUnit(array $overrides = []): array
{
    return array_merge([
        'farm_type_id' => test()->farmType->id,
        'community_id' => test()->community->id,
        'name' => 'Pen A',
        'images' => [UploadedFile::fake()->image('unit.jpg')],
    ], $overrides);
}

function photos(int $count): array
{
    return array_map(fn($i) => UploadedFile::fake()->image("unit{$i}.jpg", 800, 600), range(1, $count));
}

// both ways a unit is created: from the farmer's page and from the all-units list
function createUnitVia(string $way, array $overrides = [])
{
    return $way === 'farmer'
        ? test()->actingAs(test()->agent)->post("/agent/farmers/" . test()->farmer->uuid . "/units", photoUnit($overrides))
        : test()->actingAs(test()->agent)->post('/agent/farm-units', photoUnit(['farmer_uuid' => test()->farmer->uuid, ...$overrides]));
}

test('a unit cannot be created without a photo', function (string $way) {
    createUnitVia($way, ['images' => []])->assertSessionHasErrors('images');
    createUnitVia($way, ['images' => null])->assertSessionHasErrors('images');

    expect(FarmUnit::count())->toBe(0);
})->with(['farmer', 'list']);

test('one to three photos are accepted', function (string $way, int $count) {
    createUnitVia($way, ['images' => photos($count)])->assertSessionDoesntHaveErrors();

    expect(FarmUnit::first()->images)->toHaveCount($count);
})->with([['farmer', 1], ['farmer', 3], ['list', 1], ['list', 3]]);

test('a fourth photo is refused and nothing is created', function (string $way) {
    createUnitVia($way, ['images' => photos(4)])->assertSessionHasErrors('images');

    expect(FarmUnit::count())->toBe(0);
})->with(['farmer', 'list']);

test('a photo has to be an image', function (string $way) {
    createUnitVia($way, ['images' => [UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]])
        ->assertSessionHasErrors('images.0');

    expect(FarmUnit::count())->toBe(0);
})->with(['farmer', 'list']);

test('unit photos are kept on the private disk as webp, never the public one', function () {
    createUnitVia('farmer', ['images' => photos(2)]);

    $images = FarmUnit::first()->images;

    expect($images)->toHaveCount(2);

    foreach ($images as $image) {
        expect($image->path)->toStartWith('farm-units/')->toEndWith('.webp');
        Storage::disk('local')->assertExists($image->path);
        Storage::disk('public')->assertMissing($image->path);
    }
});

test('editing a unit does not need photos and ignores any sent', function () {
    createUnitVia('farmer');
    $unit = FarmUnit::first();

    $this->actingAs($this->agent)->put("/agent/farmers/{$this->farmer->uuid}/units/{$unit->id}", [
        'farm_type_id' => $this->farmType->id,
        'community_id' => $this->community->id,
        'name' => 'Pen B',
    ])->assertSessionDoesntHaveErrors();

    $this->actingAs($this->agent)->put("/agent/farmers/{$this->farmer->uuid}/units/{$unit->id}", photoUnit(['name' => 'Pen C', 'images' => photos(3)]))
        ->assertSessionDoesntHaveErrors();

    expect($unit->fresh()->name)->toBe('Pen C')
        ->and($unit->fresh()->images)->toHaveCount(1);
});

function unitPhotoUrl(FarmUnitImage $image): string
{
    return "/farm-unit-photos/{$image->id}";
}

test('a unit photo is served to the agent who holds the farmer and to an admin', function () {
    createUnitVia('farmer');
    $url = unitPhotoUrl(FarmUnit::first()->images->first());

    $this->actingAs($this->agent)->get($url)->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->actingAs($this->admin)->get($url)->assertOk();
});

test('everyone else gets a 404 for a unit photo', function () {
    createUnitVia('farmer');
    $url = unitPhotoUrl(FarmUnit::first()->images->first());

    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($this->otherAgent)->get($url)->assertNotFound();
    $this->actingAs($vet)->get($url)->assertNotFound();
    $this->actingAs($this->farmer->user)->get($url)->assertNotFound();
    $this->actingAs($this->admin)->get('/farm-unit-photos/999999')->assertNotFound();
});

test('a guest is sent to login for a unit photo', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);
    $image = FarmUnitImage::create(['farm_unit_id' => $unit->id, 'path' => 'farm-units/x.webp']);

    $this->get(unitPhotoUrl($image))->assertRedirect('/login');
});

test('the unit page and the approval row carry the unit photos, by the authorised route', function () {
    createUnitVia('farmer', ['images' => photos(2)]);
    $urls = FarmUnit::first()->images->map(fn($image) => unitPhotoUrl($image))->all();

    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}/units")
        ->assertInertia(fn($page) => $page->where('units.0.photo_urls', $urls));

    $item = app(ApprovalQueueService::class)->pending($this->admin)->firstWhere('kind', 'farm_unit');

    expect($item['photo_urls'])->toBe($urls);
});

test('if saving a unit fails part-way, the photos already written are deleted', function () {
    $calls = 0;
    FarmUnitImage::creating(function () use (&$calls) {
        if (++$calls === 2) {
            throw new RuntimeException('database down');
        }
    });

    $this->withoutExceptionHandling();

    try {
        expect(fn() => createUnitVia('farmer', ['images' => photos(3)]))->toThrow(RuntimeException::class);
    } finally {
        FarmUnitImage::flushEventListeners();
    }

    expect(FarmUnit::count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('farm-units'))->toBe([]);
});
