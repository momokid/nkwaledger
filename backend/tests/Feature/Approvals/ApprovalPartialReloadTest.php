<?php

use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
use App\Models\FarmUnitStockMovement;
use App\Models\User;
use App\Services\ApprovalQueueService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->submitter = User::factory()->create();
    $this->submitter->assignRole('agent');

    $this->holder = User::factory()->create();
    $this->holder->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->holder->id]);
    $this->base = "/admin/farmers/{$this->farmer->uuid}";
});

// signs one thing off, then reloads the list the way the page does and checks it
// carries only the list, the message and the badge count
function approveThenReload(string $kind, string $url): void
{
    $before = app(ApprovalQueueService::class)->countFor(test()->admin);

    test()->actingAs(test()->admin)->patch($url)->assertSessionDoesntHaveErrors();

    test()->actingAs(test()->admin)->get('/admin/approvals')
        ->assertInertia(fn($page) => $page->reloadOnly(
            ['items', 'flash', 'auth.pendingApprovals'],
            fn($reload) => $reload
                ->where('items.data', fn($items) => collect($items)->where('kind', $kind)->isEmpty())
                ->where('auth.pendingApprovals', $before - 1)
                ->missing('permissions')
                ->missing('basePath'),
        ));
}

function approvedUnit(): FarmUnit
{
    return FarmUnit::factory()->create([
        'farmer_profile_id' => test()->farmer->id,
        'created_by' => test()->submitter->id,
        'approved_at' => now(),
        'approved_by' => test()->admin->id,
    ]);
}

test('a unit approval reloads only the list, message and count', function () {
    $unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->farmer->id, 'created_by' => $this->submitter->id]);

    approveThenReload('farm_unit', "{$this->base}/units/{$unit->id}/approve");
});

test('a count approval reloads only the list, message and count', function () {
    $unit = approvedUnit();
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'recorded_by' => $this->submitter->id]);

    approveThenReload('stock', "{$this->base}/units/{$unit->id}/stocks/{$stock->id}/confirm");
});

test('a change approval reloads only the list, message and count', function () {
    $unit = approvedUnit();
    $stock = FarmUnitStock::factory()->confirmed()->create([
        'farm_unit_id' => $unit->id,
        'recorded_by' => $this->submitter->id,
        'confirmed_by' => $this->admin->id,
    ]);
    $movement = FarmUnitStockMovement::factory()->create(['farm_unit_stock_id' => $stock->id, 'recorded_by' => $this->submitter->id]);

    approveThenReload('stock_movement', "{$this->base}/units/{$unit->id}/stocks/{$stock->id}/movements/{$movement->id}/confirm");
});

test('an ID verification approval reloads only the list, message and count', function () {
    $this->farmer->forceFill([
        'identity_number_hash' => 'h',
        'identity_photo_path' => 'kyc/x.webp',
        'identity_submitted_by' => $this->submitter->id,
        'identity_submitted_at' => now(),
    ])->save();

    approveThenReload('farmer_identity', "{$this->base}/identity/verify");
});
