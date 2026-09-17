<?php

use App\Enums\MovementReason;
use App\Enums\StockSource;
use App\Models\AccountingPeriod;
use App\Models\AuditLog;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\LedgerAccount;
use App\Models\User;
use Database\Seeders\LedgerAccountSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\TransactionTemplateSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->seed(LedgerAccountSeeder::class);

    foreach (['Livestock', 'Crop', 'Aquatic'] as $name) {
        FarmTypeCategory::firstOrCreate(['name' => $name]);
    }

    $this->seed(TransactionTemplateSeeder::class);

    AccountingPeriod::create([
        'name' => 'Test Period',
        'starts_on' => now()->startOfYear()->toDateString(),
        'ends_on' => now()->endOfYear()->toDateString(),
    ]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);

    // matches one of the seeded stock-purchase/opening-balance templates, so posting
    // through PostingService can always find the template it needs
    $this->livestockType = FarmType::factory()
        ->withCategory(FarmTypeCategory::where('name', 'Livestock')->first())
        ->create();

    $this->unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->farmer->id,
        'farm_type_id' => $this->livestockType->id,
    ]);

    $this->cashAccountId = LedgerAccount::where('name', 'Cash A/C')->value('id');
});

function stockPayload(array $overrides = []): array
{
    return array_merge([
        'source' => 'purchase',
        'opening_quantity' => 200,
        'unit_of_measure' => 'birds',
        'acquisition_cost' => 4000,
        'started_on' => now()->subMonth()->toDateString(),
        'settlement_account_id' => test()->cashAccountId,
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

test('adding a stock notifies people who can confirm it, except the person who added it', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload());

    expect(\App\Models\Notification::where('user_id', $this->otherAgent->id)
        ->where('kind', 'farm_unit_stock.created')
        ->exists())->toBeTrue();

    expect(\App\Models\Notification::where('user_id', $this->agent->id)
        ->where('kind', 'farm_unit_stock.created')
        ->exists())->toBeFalse();
});

test('recording a movement notifies people who can confirm it, except the person who recorded it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload());

    expect(\App\Models\Notification::where('user_id', $this->otherAgent->id)
        ->where('kind', 'farm_unit_stock_movement.created')
        ->exists())->toBeTrue();

    expect(\App\Models\Notification::where('user_id', $this->agent->id)
        ->where('kind', 'farm_unit_stock_movement.created')
        ->exists())->toBeFalse();
});

test('the count starts at the opening quantity once confirmed', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'source' => 'opening_balance',
        'opening_quantity' => 150,
    ]));

    $stock = $this->unit->fresh()->stocks->first();
    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm");

    expect($stock->fresh()->current_quantity)->toBe('150.00');
});

// declared stock does not count on nobody's own say-so, "already had it" included
test('an opening-balance count does not count until confirmed', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'source' => 'opening_balance',
        'opening_quantity' => 150,
    ]));

    expect($this->unit->fresh()->stocks->first()->current_quantity)->toBe('0.00');
});

// the farmer is never blocked, the entry just does not count yet
test('a stock can be added to a unit that is not checked', function () {
    $unit = FarmUnit::factory()->create([
        'farmer_profile_id' => $this->farmer->id,
        'farm_type_id' => $this->livestockType->id,
    ]);

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

// only admin can confirm what an agent recorded, so this uses admin — a plain "can this be
// confirmed at all" check, not the agent-vs-agent rule (that gets its own tests below)
test('a stock can be confirmed', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->isConfirmed())->toBeTrue();
});

test('confirming a stock records who did it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm");

    expect($stock->fresh()->confirmed_by)->toBe($this->admin->id);
});

// whoever wrote the number down is not the one who checks it
test('the person who added a stock cannot confirm it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
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

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionHasErrors();
});

// an agent's own colleague cannot wave an entry through — only admin may
test('an agent cannot confirm a stock recorded by another agent', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionHasErrors();

    expect($stock->fresh()->isConfirmed())->toBeFalse();
});

// but the path stays open for when a farmer records their own entry directly
test('an agent can confirm a stock recorded by the farmer', function () {
    $farmerUser = User::factory()->create();
    $farmerUser->assignRole('farmer');

    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $farmerUser->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->isConfirmed())->toBeTrue();
});

// the audit trail names the farmer and the agent, not just a bare record id
test('confirming a stock records the farmer and agent for the admin audit trail', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm");

    $entry = AuditLog::where('action', 'farm_unit_stock.confirmed')->latest('id')->first();

    expect($entry->new_values['farmer'])->toBe(trim("{$this->farmer->user?->surname} {$this->farmer->user?->first_name}"));
    expect($entry->new_values['agent'])->toBe(trim("{$this->agent->surname} {$this->agent->first_name}"));
    expect($entry->new_values['checked_by'])->toBe(trim("{$this->admin->surname} {$this->admin->first_name}"));
});

// confirming a purchased batch has to also confirm the opening number that started it, or
// the count would stay at zero forever even after approval
test('confirming a purchased stock also confirms its opening movement', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'source' => App\Enums\StockSource::Purchase,
        'opening_quantity' => 30,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm");

    $opening = $stock->movements()->where('reason', MovementReason::Opening)->first();

    expect($opening->isConfirmed())->toBeTrue();
    expect($stock->fresh()->current_quantity)->toBe('30.00');
});

test('a movement can be recorded', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 200]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload())
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->current_quantity)->toBe('195.00');
});

test('an unconfirmed birth does not add to the count yet', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'birth',
        'quantity' => 3,
    ]));

    expect($stock->fresh()->current_quantity)->toBe('10.00');
});

test('a confirmed birth adds to the count', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'reason' => MovementReason::Birth,
        'quantity' => 3,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertSessionDoesntHaveErrors();

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

// selling or losing more than what is on record would let a farmer report a sale that
// never happened, or hide a shortfall behind a loss bigger than what was ever there
test('a death cannot take away more than the stock currently has', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'death',
        'quantity' => 15,
    ]))->assertSessionHasErrors('quantity');

    expect($stock->fresh()->current_quantity)->toBe('10.00');
});

test('a sale recorded directly on a stock cannot exceed what it has', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'sale',
        'quantity' => 11,
    ]))->assertSessionHasErrors('quantity');
});

test('a downward correction cannot take away more than the stock has', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'correction',
        'quantity' => 12,
        'is_increase' => false,
    ]))->assertSessionHasErrors('quantity');
});

// a purchased batch nobody has confirmed yet has nothing recorded against it either
test('nothing can be reported sold, lost, or dead against an unconfirmed purchase', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'source' => App\Enums\StockSource::Purchase,
        'opening_quantity' => 10,
    ]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'death',
        'quantity' => 1,
    ]))->assertSessionHasErrors('quantity');
});

// an addition is never checked against what is already there — that would make no sense
test('an addition is not checked against the current quantity', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'birth',
        'quantity' => 999,
    ]))->assertSessionDoesntHaveErrors();
});

test('a decrease exactly equal to the current quantity is allowed', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id, 'opening_quantity' => 10]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements", movementPayload([
        'reason' => 'death',
        'quantity' => 10,
    ]))->assertSessionDoesntHaveErrors();
});

test('a movement can be confirmed', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($movement->fresh()->isConfirmed())->toBeTrue();
});

test('the person who recorded a movement cannot confirm it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm")
        ->assertSessionHasErrors();

    expect($movement->fresh()->isConfirmed())->toBeFalse();
});

// an agent's own colleague cannot wave a movement through either — only admin may
test('an agent cannot confirm a movement recorded by another agent', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
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

// the button offered matches what the backend will actually allow
test('an agent is not offered confirm on a colleague-recorded stock', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('stocks.0.can_confirm', false));
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
test('the page sends the accounts money can sit in', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->has('settlementAccounts'));
});

// a synthetic option the frontend adds, not one of these real ledger accounts
test('never offers Accounts Receivable or Accounts Payable as a settlement account choice', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page
            ->where('settlementAccounts', fn($accounts) => ! collect($accounts)
                ->pluck('name')
                ->intersect(['Accounts Receivable', 'Accounts Payable'])
                ->isNotEmpty()));
});

test('the page tells the category of the farm type', function () {
    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->has('unit.farm_type_category'));
});

test('a stock can be rejected', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Wrong number of animals',
    ])->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->isRejected())->toBeTrue();
});

test('a reason is required to reject a stock', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => '',
    ])->assertSessionHasErrors('reason');
});

// whoever wrote the number down is not the one who checks it, rejection included
test('the person who added a stock cannot reject it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->admin->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Wrong number',
    ])->assertSessionHasErrors();

    expect($stock->fresh()->isRejected())->toBeFalse();
});

test('a user without the confirm permission cannot reject a stock', function () {
    $vet = User::factory()->create();
    $vet->assignRole('vet');
    $vet->givePermissionTo('farm-units.view');

    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);

    $this->actingAs($vet)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Wrong number',
    ])->assertForbidden();
});

// an agent's own colleague cannot send back an entry either — only admin may
test('an agent cannot reject a stock recorded by another agent', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Wrong number',
    ])->assertSessionHasErrors();

    expect($stock->fresh()->isRejected())->toBeFalse();
});

test('a movement can be rejected', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/reject", [
        'reason' => 'Wrong reason chosen',
    ])->assertSessionDoesntHaveErrors();

    expect($movement->fresh()->isRejected())->toBeTrue();
});

test('the person who recorded a movement cannot reject it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->agent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/reject", [
        'reason' => 'Wrong reason',
    ])->assertSessionHasErrors();
});

// an agent's own colleague cannot send back a movement either — only admin may
test('an agent cannot reject a movement recorded by another agent', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->agent)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/reject", [
        'reason' => 'Wrong reason chosen',
    ])->assertSessionHasErrors();

    expect($movement->fresh()->isRejected())->toBeFalse();
});

test('the page shows a rejected stock', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $stock->reject($this->agent->id, 'Wrong number of animals');

    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('stocks.0.is_rejected', true)
            ->where('stocks.0.rejection_reason', 'Wrong number of animals'));
});

test('the page shows a rejected movement', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $movement->reject($this->agent->id, 'Wrong reason chosen');

    $this->actingAs($this->admin)->get("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks")
        ->assertInertia(fn($page) => $page->where('stocks.0.movements.0.is_rejected', true)
            ->where('stocks.0.movements.0.rejection_reason', 'Wrong reason chosen'));
});

test('rejecting a stock notifies whoever recorded it', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Wrong number of animals',
    ]);

    expect(\App\Models\Notification::where('user_id', $this->otherAgent->id)
        ->where('kind', 'farm_unit_stock.rejected')
        ->exists())->toBeTrue();
});

test('rejecting a movement notifies whoever recorded it', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->otherAgent->id,
    ]);

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/movements/{$movement->id}/reject", [
        'reason' => 'Wrong reason chosen',
    ]);

    expect(\App\Models\Notification::where('user_id', $this->otherAgent->id)
        ->where('kind', 'farm_unit_stock_movement.rejected')
        ->exists())->toBeTrue();
});

// --- routed through PostingService: real ledger postings, cash/credit, and the full
// declare -> confirm lifecycle this now shares with a real farmer-recorded purchase ---

test('a purchase declaration posts a real transaction, not just a bare stock row', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload())
        ->assertSessionDoesntHaveErrors();

    $transaction = \App\Models\Transaction::where('farmer_profile_id', $this->farmer->id)->first();

    expect($transaction)->not->toBeNull();
    expect($transaction->transaction_type)->toBe('EXPENSE');
    expect($transaction->amount_minor)->toBe(400000);
    expect($transaction->quantity_purchased)->toBe('200.00');
});

test('the auto-created stock carries the acquisition cost from the transaction amount', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'acquisition_cost' => 500,
    ]));

    expect($this->unit->fresh()->stocks->first()->acquisition_cost)->toBe('500.00');
});

test('a purchase paid in cash settles against cash, not credit', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload());

    $transaction = \App\Models\Transaction::where('farmer_profile_id', $this->farmer->id)->first();

    expect($transaction->is_credit)->toBeFalse();
    expect((int) $transaction->settlement_account_id)->toBe((int) $this->cashAccountId);
});

test('cash payment requires saying where the money went', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'settlement_account_id' => null,
    ]))->assertSessionHasErrors('settlement_account_id');
});

test('a purchase can be put on credit instead of paid in cash', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'is_credit' => true,
        'settlement_account_id' => null,
    ]))->assertSessionDoesntHaveErrors();

    $transaction = \App\Models\Transaction::where('farmer_profile_id', $this->farmer->id)->first();
    $payable = LedgerAccount::where('name', 'Accounts Payable')->value('id');

    expect($transaction->is_credit)->toBeTrue();
    expect((int) $transaction->settlement_account_id)->toBe((int) $payable);
});

test('a credit purchase can be partially settled, same as any other credit purchase', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'is_credit' => true,
        'settlement_account_id' => null,
    ]));

    $transaction = \App\Models\Transaction::where('farmer_profile_id', $this->farmer->id)->first();
    $settlements = app(\App\Services\Ledger\CreditSettlementService::class);

    expect($settlements->outstandingAmount($transaction))->toBe(400000);

    $settlements->settle(
        original: $transaction,
        amountMinor: 150000,
        settlementAccountId: $this->cashAccountId,
        transactionDate: now()->toDateString(),
        recordedBy: $this->admin->id,
    );

    expect($settlements->outstandingAmount($transaction->fresh()))->toBe(250000);
});

// "already had it" is an asset gained without cash leaving anyone's hand, so it credits
// owner funds and never offers - or needs - a cash/credit choice at all
test('an opening-balance declaration posts against Stated Capital, never cash or credit', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'source' => 'opening_balance',
        'settlement_account_id' => null,
    ]))->assertSessionDoesntHaveErrors();

    $transaction = \App\Models\Transaction::where('farmer_profile_id', $this->farmer->id)->first();
    $statedCapital = LedgerAccount::where('name', 'Stated Capital')->value('id');

    expect($transaction->is_credit)->toBeFalse();
    expect($transaction->settlement_account_id)->toBeNull();
    expect($transaction->template->credit_account_id)->toBe($statedCapital);
});

test('a farm unit whose category has no matching template is refused clearly', function () {
    $type = FarmType::factory()->create(['category_id' => null]);
    $unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->farmer->id,
        'farm_type_id' => $type->id,
    ]);

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$unit->id}/stocks", stockPayload())
        ->assertSessionHasErrors('source');
});

test('a zero-cost declaration is refused, same as a real purchase', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'acquisition_cost' => 0,
    ]))->assertSessionHasErrors('acquisition_cost');
});

// the whole point: declared stock now goes through the same confirm/reject queue a real
// farmer-recorded purchase already uses, end to end over HTTP
test('a declared purchase counts only after an admin confirms it, then blocks a second confirm', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 75,
    ]));

    $stock = $this->unit->fresh()->stocks->first();
    expect($stock->current_quantity)->toBe('0.00');

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->current_quantity)->toBe('75.00');
    expect($stock->fresh()->countsTowardCredit())->toBeTrue();

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/confirm")
        ->assertSessionHasErrors();
});

test('a declared purchase can be rejected instead, and never counts', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 75,
    ]));

    $stock = $this->unit->fresh()->stocks->first();

    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$stock->id}/reject", [
        'reason' => 'Numbers do not match the field visit',
    ])->assertSessionDoesntHaveErrors();

    expect($stock->fresh()->isRejected())->toBeTrue();
    expect($stock->fresh()->current_quantity)->toBe('0.00');
});

test('a purchase declaration adds to the most recently started active batch instead of starting a new one', function () {
    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 100,
    ]));
    $first = $this->unit->fresh()->stocks->first();
    $this->actingAs($this->admin)->patch("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks/{$first->id}/confirm");

    $this->actingAs($this->agent)->post("/admin/farmers/{$this->farmer->uuid}/units/{$this->unit->id}/stocks", stockPayload([
        'opening_quantity' => 40,
    ]));

    expect($this->unit->fresh()->stocks)->toHaveCount(1);

    $movement = $first->fresh()->movements()->where('reason', MovementReason::Purchase)->first();
    expect($movement)->not->toBeNull();
    expect($movement->quantity)->toBe('40.00');
});
