<?php

use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
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

    $drClass = LedgerClass::create(['name' => 'Dr']);

    $assetsCategory = LedgerCategory::create([
        'name' => 'Assets',
        'class_id' => $drClass->id,
    ]);

    $this->subcategory = LedgerSubcategory::create([
        'category_id' => $assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $this->control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $this->glType = LedgerType::create(['name' => 'GL']);

    $this->validPayload = [
        'name' => 'Cash & MoMo',
        'account_code' => '1001',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->subcategory->id,
        'type_id' => $this->glType->id,
    ];
});

it('lists ledger accounts for a user with view permission', function () {
    LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($this->admin)->get(route('admin.ledger-accounts.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/LedgerAccounts/Index')
            ->has('ledgerAccounts.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.ledger-accounts.index'));

    $response->assertForbidden();
});

it('creates a ledger account with valid data', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), $this->validPayload);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_accounts', [
        'name' => 'Cash & MoMo',
        'account_code' => '1001',
        'control_id' => $this->control->id,
        'subcategory_id' => $this->subcategory->id,
        'type_id' => $this->glType->id,
    ]);
});

it('creates a ledger account without an account code', function () {
    $payload = $this->validPayload;
    unset($payload['account_code']);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), $payload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
    $this->assertDatabaseHas('ledger_accounts', ['name' => 'Cash & MoMo', 'account_code' => null]);
});

it('rejects a duplicate account name on create', function () {
    LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), $this->validPayload);

    $response->assertSessionHasErrors('name');
});

it('rejects a nonexistent control on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), [
        ...$this->validPayload,
        'control_id' => 9999,
    ]);

    $response->assertSessionHasErrors('control_id');
});

it('rejects a nonexistent subcategory on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), [
        ...$this->validPayload,
        'subcategory_id' => 9999,
    ]);

    $response->assertSessionHasErrors('subcategory_id');
});

it('rejects a nonexistent type on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.ledger-accounts.store'), [
        ...$this->validPayload,
        'type_id' => 9999,
    ]);

    $response->assertSessionHasErrors('type_id');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->post(route('admin.ledger-accounts.store'), $this->validPayload);

    $response->assertForbidden();
    $this->assertDatabaseMissing('ledger_accounts', ['name' => 'Cash & MoMo']);
});

it('updates a ledger account with valid data', function () {
    $account = LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-accounts.update', $account), [
        ...$this->validPayload,
        'name' => 'Mobile Money',
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('ledger_accounts', ['id' => $account->id, 'name' => 'Mobile Money']);
});

it('allows updating an account without changing its name', function () {
    $account = LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($this->admin)->put(route('admin.ledger-accounts.update', $account), $this->validPayload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('soft deletes a non-system ledger account', function () {
    $account = LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($this->admin)->delete(route('admin.ledger-accounts.destroy', $account));

    $response->assertRedirect();
    $this->assertSoftDeleted('ledger_accounts', ['id' => $account->id]);
});

it('does not delete a system ledger account', function () {
    $account = LedgerAccount::create([...$this->validPayload, 'is_system' => true]);

    $this->actingAs($this->admin)->delete(route('admin.ledger-accounts.destroy', $account));

    $this->assertDatabaseHas('ledger_accounts', ['id' => $account->id, 'deleted_at' => null]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create', 'ledger-accounts.update']);
    $account = LedgerAccount::create($this->validPayload);

    $response = $this->actingAs($user)->delete(route('admin.ledger-accounts.destroy', $account));

    $response->assertForbidden();
    $this->assertDatabaseHas('ledger_accounts', ['id' => $account->id]);
});
