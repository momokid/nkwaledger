<?php

use App\Enums\MovementReason;
use App\Enums\StockSource;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $this->farmer->id]);
});

function stockPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'purchase',
        'opening_quantity' => 200,
        'unit_of_measure' => 'birds',
        'acquisition_cost' => 4000,
        'started_on' => now()->subMonth()->toDateString(),
    ], $overrides);
}

function movementPayload(array $overrides = []): array
{
    return array_merge([
        'reason' => 'death',
        'quantity' => 5,
        'occurred_on' => now()->subDays(2)->toDateString(),
        'note' => null,
    ], $overrides);
}

test('a guest is redirected to login', function () {
    $this->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")->assertRedirect('/login');
});

test('a user without the view permission is forbidden', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');

    $this->actingAs($vet)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertForbidden();
});

test('an admin sees the list page', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertOk()
        ->assertInertia(fn($page) => $page->component('Admin/FarmUnits/Stocks')->has('stocks'));
});

test('the page carries the unit it belongs to', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('unit.id', $this->unit->id));
});

test('an agent cannot open stocks for a farmer they do not hold', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->otherAgent->id]);
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $other->id]);

    $this->actingAs($this->agent)->get("/agent/farmers/{$other->uuid}/units/{$unit->id}/stocks")
        ->assertNotFound();
});

test('a unit from another farmer is not found here', function () {
    $other = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $unit = FarmUnit::factory()->approved()->create(['farmer_profile_id' => $other->id]);

    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}/units/{$unit->id}/stocks")
        ->assertNotFound();
});

test('the page is told which frame to wear', function () {
    $this->actingAs($this->agent)->get("/agent/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('layout', 'agent'));
});

test('a stock can be added', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload())
        ->assertSessionDoesntHaveErrors();

    expect($this->unit->fresh()->stocks)->toHaveCount(1);
});

test('adding a stock records who did it', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload());

    expect($this->unit->fresh()->stocks->first()->recorded_by)->toBe($this->agent->id);
});

test('a new stock is not confirmed', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload());

    expect($this->unit->fresh()->stocks->first()->isConfirmed())->toBeFalse();
});

test('the count starts at the opening quantity', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 150,
    ]));

    expect($this->unit->fresh()->stocks->first()->current_quantity)->toBe('150.00');
});

// the farmer is never blocked, the entry just does not count yet
test('a stock can be added to a unit that is not checked', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/stocks", stockPayload())
        ->assertSessionDoesntHaveErrors();

    expect($unit->fresh()->stocks)->toHaveCount(1);
});

test('an opening quantity above zero is required', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 0,
    ]))->assertSessionHasErrors('opening_quantity');
});

test('a start date is required', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'started_on' => null,
    ]))->assertSessionHasErrors('started_on');
});

test('a start date in the future is refused', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'started_on' => now()->addWeek()->toDateString(),
    ]))->assertSessionHasErrors('started_on');
});

test('an unknown source is refused', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'source' => 'inherited',
    ]))->assertSessionHasErrors('source');
});

test('a stock the farmer already had can be recorded', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'source' => 'opening_balance',
    ]))->assertSessionDoesntHaveErrors();

    expect($this->unit->fresh()->stocks->first()->source)->toBe(StockSource::OpeningBalance);
});

test('a user without the create permission cannot add a stock', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $this->actingAs($vet)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload())
        ->assertForbidden();
});

test('a stock can be confirmed', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->isConfirmed())->toBeTrue();
});

test('confirming a stock records who did it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm");

    expect($stock->fresh()->confirmed_by)->toBe($this->agent->id);
});

// whoever wrote the number down is not the one who checks it
test('the person who added a stock cannot confirm it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionHasErrors();

    expect($stock->fresh()->isConfirmed())->toBeFalse();
});

test('a user without the confirm permission cannot confirm a stock', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($vet)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertForbidden();
});

test('an already confirmed stock cannot be confirmed again', function () {
    $stock = FarmUnitStock::factory()->confirmed()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionHasErrors();
});

test('a movement can be recorded', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 200]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload())
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->current_quantity)->toBe('195.00');
});

test('a birth adds to the count', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'birth',
        'quantity' => 3,
    ]));

    expect($stock->fresh()->current_quantity)->toBe('13.00');
});

test('a movement records who did it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload());

    expect($stock->movements()->latest('id')->first()->recorded_by)->toBe($this->agent->id);
});

test('a new movement is not confirmed', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload());

    expect($stock->movements()->latest('id')->first()->isConfirmed())->toBeFalse();
});

test('a quantity above zero is required', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'quantity' => 0,
    ]))->assertSessionHasErrors('quantity');
});

test('a date in the future is refused', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'occurred_on' => now()->addWeek()->toDateString(),
    ]))->assertSessionHasErrors('occurred_on');
});

// the starting count is written by the system, nobody picks it
test('the starting count cannot be chosen as a reason', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'opening',
    ]))->assertSessionHasErrors('reason');
});

// a miscount can go either way, so this one is told which
test('a correction needs a direction', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'correction',
        'is_increase' => null,
    ]))->assertSessionHasErrors('is_increase');
});

test('a downward correction takes away', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 100]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'correction',
        'quantity' => 4,
        'is_increase' => false,
    ]));

    expect($stock->fresh()->current_quantity)->toBe('96.00');
});

test('a movement can be confirmed', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($movement->fresh()->isConfirmed())->toBeTrue();
});

test('the person who recorded a movement cannot confirm it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertSessionHasErrors();

    expect($movement->fresh()->isConfirmed())->toBeFalse();
});

test('a movement on another stock is not found here', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $otherStock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create(['farm_unit_stock_id' => $otherStock->id]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertNotFound();
});

test('the page lists movements under each stock', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
    ]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->has('stocks.0.movements', 2));
});

test('the page says what this user may do', function () {
    $this->actingAs($this->agent)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('permissions.create', true)
            ->where('permissions.confirm', true));
});

test('expected_ready_on is optional', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'expected_ready_on' => null,
    ]))->assertSessionDoesntHaveErrors('expected_ready_on');
});

test('expected_ready_on is stored when given', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'expected_ready_on' => '2026-12-01',
    ]));

    expect($this->unit->fresh()->stocks->first()->expected_ready_on->toDateString())->toBe('2026-12-01');
});

test('expected_ready_on cannot be before the start date', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'started_on' => '2026-06-01',
        'expected_ready_on' => '2026-05-01',
    ]))->assertSessionHasErrors('expected_ready_on');
});

test('an invalid expected_ready_on is refused', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'expected_ready_on' => 'not-a-date',
    ]))->assertSessionHasErrors('expected_ready_on');
});
test('the page shows the expected ready date for each stock', function () {
    FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'expected_ready_on' => '2026-12-01',
    ]);

    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('stocks.0.expected_ready_on', '2026-12-01'));
});
test('the page tells the category of the farm type', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->has('unit.farm_type_category'));
});
