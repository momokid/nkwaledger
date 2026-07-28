<?php

use App\Models\LedgerCategory;
use App\Models\LedgerFundamentalType;
use App\Models\LedgerSubcategory;
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

    $asset = LedgerFundamentalType::where('name', 'Asset')->first();
    $this->assetsCategory = LedgerCategory::create([
        'fundamental_type_id' => $asset->id,
        'name' => 'Assets',
        'type' => 'GL',
    ]);

    $income = LedgerFundamentalType::where('name', 'Income')->first();
    $this->incomeCategory = LedgerCategory::create([
        'fundamental_type_id' => $income->id,
        'name' => 'Income',
        'type' => 'Income',
    ]);
});

it('lists ledger subcategories for a user with view permission', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-subcategories.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerSubcategories/Index')
            ->has('ledgerSubcategories.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-subcategories.index'));

    $response->assertForbidden();
});

it('creates a ledger subcategory with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-subcategories.store'), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_subcategories', [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);
});

it('rejects a duplicate name within the same category on create', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-subcategories.store'), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response->assertSessionHasErrors('name');
});

it('allows the same name in a different category on create', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'General',
    ]);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-subcategories.store'), [
        'category_id' => $this->incomeCategory->id,
        'name' => 'General',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a nonexistent category on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-subcategories.store'), [
        'category_id' => 9999,
        'name' => 'Short Term Asset',
    ]);

    $response->assertSessionHasErrors('category_id');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-subcategories.store'), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_subcategories', ['name' => 'Short Term Asset']);
});

it('updates a ledger subcategory with valid data', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-subcategories.update', $subcategory), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Long Term Asset',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_subcategories', ['id' => $subcategory->id, 'name' => 'Long Term Asset']);
});

it('allows updating a subcategory without changing its name', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-subcategories.update', $subcategory), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate name within the same category on update', function () {
    LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Long Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-subcategories.update', $subcategory), [
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response->assertSessionHasErrors('name');
});

it('allows moving a subcategory to a different category even if the name exists there under a different record', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'General',
    ]);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-subcategories.update', $subcategory), [
        'category_id' => $this->incomeCategory->id,
        'name' => 'General',
    ]);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('soft deletes a ledger subcategory', function () {
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-subcategories.destroy', $subcategory));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_subcategories', ['id' => $subcategory->id]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $subcategory = LedgerSubcategory::create([
        'category_id' => $this->assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $response = $this->actingAs($user)->delete(route('admin.ledger-subcategories.destroy', $subcategory));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_subcategories', ['id' => $subcategory->id]);
});
