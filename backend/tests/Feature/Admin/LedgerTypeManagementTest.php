<?php

use App\Models\LedgerType;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'ledger-accounts.view',
        'ledger-accounts.create',
        'ledger-accounts.update',
        'ledger-accounts.delete',
    ]);
});

it('lists ledger types for a user with view permission', function () {
    LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-types.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerTypes/Index')
            ->has('ledgerTypes.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-types.index'));

    $response->assertForbidden();
});

it('creates a ledger type with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-types.store'), [
        'name' => 'Income',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_types', ['name' => 'Income']);
});

it('rejects a duplicate ledger type name on create', function () {
    LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-types.store'), [
        'name' => 'GL',
    ]);

    $response->assertSessionHasErrors('name');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-types.store'), [
        'name' => 'Income',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_types', ['name' => 'Income']);
});

it('updates a ledger type with valid data', function () {
    $type = LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-types.update', $type), [
        'name' => 'General Ledger',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_types', ['id' => $type->id, 'name' => 'General Ledger']);
});

it('allows updating a ledger type without changing its name', function () {
    $type = LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-types.update', $type), [
        'name' => 'GL',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name on update', function () {
    LedgerType::create(['name' => 'GL']);
    $type = LedgerType::create(['name' => 'Income']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-types.update', $type), [
        'name' => 'GL',
    ]);

    $response->assertSessionHasErrors('name');
});

it('soft deletes a ledger type', function () {
    $type = LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-types.destroy', $type));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_types', ['id' => $type->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $type = LedgerType::create(['name' => 'GL']);

    $response = $this->actingAs($user)->delete(route('admin.ledger-types.destroy', $type));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_types', ['id' => $type->id]);
});
