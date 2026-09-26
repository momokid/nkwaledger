<?php

use App\Models\FarmerProfile;
use App\Models\FarmType;
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

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');

    $this->farmer = FarmerProfile::factory()->create(['assigned_agent_id' => $this->agent->id]);
    $this->farmType = FarmType::factory()->withCategory()->create();
    $this->unit = FarmUnit::factory()->approved()->create([
        'farmer_profile_id' => $this->farmer->id,
        'farm_type_id' => $this->farmType->id,
    ]);

    $this->queue = app(ApprovalQueueService::class);
});

test('a rejected stock does not appear as pending', function () {
    $stock = FarmUnitStock::factory()->create([
        'farm_unit_id' => $this->unit->id,
        'recorded_by' => $this->agent->id,
    ]);

    $stock->reject($this->admin->id, 'Wrong number');

    $items = $this->queue->pending($this->admin);

    expect($items->where('kind', 'stock')->where('id', $stock->id))->toHaveCount(0);
});

test('a rejected movement does not appear as pending', function () {
    $stock = FarmUnitStock::factory()->create(['farm_unit_id' => $this->unit->id]);
    $movement = FarmUnitStockMovement::factory()->create([
        'farm_unit_stock_id' => $stock->id,
        'recorded_by' => $this->agent->id,
    ]);

    $movement->reject($this->admin->id, 'Wrong reason');

    $items = $this->queue->pending($this->admin);

    expect($items->where('kind', 'stock_movement')->where('id', $movement->id))->toHaveCount(0);
});

test('a pending commission appears for admin, marked approvable only by admin', function () {
    $kiosk = \App\Models\Kiosk::factory()->confirmed()->create();
    $order = \App\Models\Order::factory()->create([
        'kiosk_id' => $kiosk->id,
        'farmer_profile_id' => $this->farmer->id,
        'farm_unit_id' => $this->unit->id,
    ]);
    $commission = \App\Models\Commission::factory()->create([
        'order_id' => $order->id,
        'agent_id' => $this->agent->id,
        'verifies_farmer' => true,
    ]);

    $adminItems = $this->queue->pending($this->admin);
    $agentItems = $this->queue->pending($this->agent);

    $adminRow = $adminItems->firstWhere('id', $commission->id);
    $agentRow = $agentItems->firstWhere('id', $commission->id);

    expect($adminRow['kind'])->toBe('commission')
        ->and($adminRow['can_approve'])->toBeTrue()
        ->and($agentRow['can_approve'])->toBeFalse();
});

test('an approved commission no longer appears as pending', function () {
    $kiosk = \App\Models\Kiosk::factory()->confirmed()->create();
    $order = \App\Models\Order::factory()->create([
        'kiosk_id' => $kiosk->id,
        'farmer_profile_id' => $this->farmer->id,
        'farm_unit_id' => $this->unit->id,
    ]);
    $commission = \App\Models\Commission::factory()->create([
        'order_id' => $order->id,
        'agent_id' => $this->agent->id,
        'status' => \App\Enums\CommissionStatus::Approved,
    ]);

    $items = $this->queue->pending($this->admin);

    expect($items->where('kind', 'commission')->where('id', $commission->id))->toHaveCount(0);
});
