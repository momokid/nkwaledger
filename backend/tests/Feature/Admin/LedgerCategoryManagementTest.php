<?php

use App\Models\LedgerCategory;
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

    $this->drClass = LedgerClass::create(['name' => 'Dr']);
    $this->crClass = LedgerClass::create(['name' => 'Cr']);
});

it('lists ledger categories for a user with view permission', function () {
    LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-categories.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerCategories/Index')
            ->has('ledgerCategories.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-categories.index'));

    $response->assertForbidden();
});

it('creates a ledger category with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'name' => 'Income',
        'class_id' => $this->crClass->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', [
        'name' => 'Income',
        'class_id' => $this->crClass->id,
    ]);
});

it('rejects a duplicate ledger category name on create', function () {
    LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects a nonexistent class on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'name' => 'Assets',
        'class_id' => 9999,
    ]);

    $response->assertSessionHasErrors('class_id');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-categories.store'), [
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_categories', ['name' => 'Assets']);
});

it('updates a ledger category with valid data', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'name' => 'Fixed Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id, 'name' => 'Fixed Assets']);
});

it('updates the class on an existing category', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'name' => 'Assets',
        'class_id' => $this->crClass->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id, 'class_id' => $this->crClass->id]);
});

it('allows updating a ledger category without changing its name', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name on update', function () {
    LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);
    $category = LedgerCategory::create([
        'name' => 'Income',
        'class_id' => $this->crClass->id,
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'name' => 'Assets',
        'class_id' => $this->crClass->id,
    ]);

    $response->assertSessionHasErrors('name');
});

it('soft deletes a ledger category', function () {
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-categories.destroy', $category));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_categories', ['id' => $category->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $category = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $this->drClass->id,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.ledger-categories.destroy', $category));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id]);
});
