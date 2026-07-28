<?php

use App\Models\LedgerClass;
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

it('lists ledger classes for a user with view permission', function () {
    LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-classes.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerClasses/Index')
            ->has('ledgerClasses.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-classes.index'));

    $response->assertForbidden();
});

it('creates a ledger class with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-classes.store'), [
        'name' => 'Cr',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_classes', ['name' => 'Cr']);
});

it('rejects a duplicate ledger class name on create', function () {
    LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-classes.store'), [
        'name' => 'Dr',
    ]);

    $response->assertSessionHasErrors('name');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-classes.store'), [
        'name' => 'Cr',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_classes', ['name' => 'Cr']);
});

it('updates a ledger class with valid data', function () {
    $class = LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-classes.update', $class), [
        'name' => 'Debit',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_classes', ['id' => $class->id, 'name' => 'Debit']);
});

it('allows updating a ledger class without changing its name', function () {
    $class = LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-classes.update', $class), [
        'name' => 'Dr',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name on update', function () {
    LedgerClass::create(['name' => 'Dr']);
    $class = LedgerClass::create(['name' => 'Cr']);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-classes.update', $class), [
        'name' => 'Dr',
    ]);

    $response->assertSessionHasErrors('name');
});

it('soft deletes a ledger class', function () {
    $class = LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-classes.destroy', $class));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_classes', ['id' => $class->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $class = LedgerClass::create(['name' => 'Dr']);

    $response = $this->actingAs($user)->delete(route('admin.ledger-classes.destroy', $class));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_classes', ['id' => $class->id]);
});
