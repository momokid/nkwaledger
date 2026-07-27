<?php

use App\Models\LedgerControl;
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

it('lists ledger controls for a user with view permission', function () {
    LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-controls.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerControls/Index')
            ->has('ledgerControls.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-controls.index'));

    $response->assertForbidden();
});

it('creates a ledger control with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-controls.store'), [
        'name' => 'Revenue Ctrl',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_controls', ['name' => 'Revenue Ctrl']);
});

it('rejects a duplicate ledger control name on create', function () {
    LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-controls.store'), [
        'name' => 'Cash Ctrl',
    ]);

    $response->assertSessionHasErrors('name');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-controls.store'), [
        'name' => 'Revenue Ctrl',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_controls', ['name' => 'Revenue Ctrl']);
});

it('updates a ledger control with valid data', function () {
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-controls.update', $control), [
        'name' => 'Cash Control',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_controls', ['id' => $control->id, 'name' => 'Cash Control']);
});

it('allows updating a ledger control without changing its name', function () {
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-controls.update', $control), [
        'name' => 'Cash Ctrl',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name on update', function () {
    LedgerControl::create(['name' => 'Cash Ctrl']);
    $control = LedgerControl::create(['name' => 'Revenue Ctrl']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-controls.update', $control), [
        'name' => 'Cash Ctrl',
    ]);

    $response->assertSessionHasErrors('name');
});

it('soft deletes a ledger control', function () {
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-controls.destroy', $control));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_controls', ['id' => $control->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $control = LedgerControl::create(['name' => 'Cash Ctrl']);

    $response = $this->actingAs($user)->delete(route('admin.ledger-controls.destroy', $control));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_controls', ['id' => $control->id]);
});
