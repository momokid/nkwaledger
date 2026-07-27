<?php

use App\Models\LedgerCategory;
use App\Models\LedgerFundamentalType;
use App\Models\User;
use Database\Seeders\LedgerFundamentalTypeSeeder;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
    $this->seed(LedgerFundamentalTypeSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'ledger-accounts.view',
        'ledger-accounts.create',
        'ledger-accounts.update',
        'ledger-accounts.delete',
    ]);

    $this->asset = LedgerFundamentalType::where('name', 'Asset')->first();
    $this->income = LedgerFundamentalType::where('name', 'Income')->first();
});

it('lists ledger categories for a user with view permission', function () {
    LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
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
        'fundamental_type_id' => $this->income->id,
        'name' => 'Income',
        'type' => 'Income',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', [
        'name' => 'Income',
        'class' => 'credit',
    ]);
});

it('ignores a class value submitted directly and derives it from the fundamental type', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
        'class' => 'credit',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', [
        'name' => 'Assets',
        'class' => 'debit',
    ]);
});

it('rejects a duplicate ledger category name on create', function () {
    LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response->assertSessionHasErrors('name');
});

it('rejects a nonexistent fundamental type on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-categories.store'), [
        'fundamental_type_id' => 9999,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response->assertSessionHasErrors('fundamental_type_id');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-categories.store'), [
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_categories', ['name' => 'Assets']);
});

it('updates a ledger category with valid data', function () {
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Fixed Assets',
        'type' => 'GL',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id, 'name' => 'Fixed Assets']);
});

it('re-derives class when the fundamental type changes on update', function () {
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'fundamental_type_id' => $this->income->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id, 'class' => 'credit']);
});

it('allows updating a ledger category without changing its name', function () {
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name on update', function () {
    LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->income->id,
        'name' => 'Income',
        'type' => 'Income',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-categories.update', $category), [
        'fundamental_type_id' => $this->income->id,
        'name' => 'Assets',
        'type' => 'Income',
    ]);

    $response->assertSessionHasErrors('name');
});

it('soft deletes a ledger category', function () {
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-categories.destroy', $category));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_categories', ['id' => $category->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $category = LedgerCategory::create([
        'fundamental_type_id' => $this->asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.ledger-categories.destroy', $category));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_categories', ['id' => $category->id]);
});
