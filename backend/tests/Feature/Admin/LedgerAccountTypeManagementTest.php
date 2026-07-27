<?php

use App\Models\LedgerAccountType;
use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "ledger-accounts.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when listing ledger account types', function () {
    $this->get('/admin/ledger-account-types')->assertRedirect('/login');
});

test('a user without ledger-accounts.view cannot list ledger account types', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/ledger-account-types')->assertForbidden();
});

test('a user with ledger-accounts.view can list ledger account types', function () {
    LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->get('/admin/ledger-account-types')->assertOk();
});

test('a user without ledger-accounts.create cannot create a ledger account type', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->post('/admin/ledger-account-types', [
        'name' => 'Liability',
        'normal_balance' => 'credit',
    ])->assertForbidden();
});

test('a user with ledger-accounts.create can create a ledger account type', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-account-types', [
        'name' => 'Liability',
        'normal_balance' => 'credit',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('ledger_account_types', ['name' => 'Liability', 'normal_balance' => 'credit']);
});

test('creating a ledger account type with an invalid normal_balance fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-account-types', [
        'name' => 'Mystery',
        'normal_balance' => 'sideways',
    ])->assertSessionHasErrors('normal_balance');
});

test('creating a ledger account type with a duplicate name fails validation', function () {
    LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-account-types', [
        'name' => 'Asset',
        'normal_balance' => 'credit',
    ])->assertSessionHasErrors('name');
});

test('a user without ledger-accounts.update cannot update a ledger account type', function () {
    $type = LedgerAccountType::create(['name' => 'Equity', 'normal_balance' => 'credit']);
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->put("/admin/ledger-account-types/{$type->id}", [
        'name' => 'Owner Equity',
        'normal_balance' => 'credit',
    ])->assertForbidden();
});

test('a user with ledger-accounts.update can update a ledger account type', function () {
    $type = LedgerAccountType::create(['name' => 'Equity', 'normal_balance' => 'credit']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.update']);

    $this->actingAs($user)->put("/admin/ledger-account-types/{$type->id}", [
        'name' => 'Owner Equity',
        'normal_balance' => 'credit',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('ledger_account_types', ['id' => $type->id, 'name' => 'Owner Equity']);
});

test('updating a ledger account type with an invalid normal_balance fails validation', function () {
    $type = LedgerAccountType::create(['name' => 'Equity', 'normal_balance' => 'credit']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.update']);

    $this->actingAs($user)->put("/admin/ledger-account-types/{$type->id}", [
        'name' => 'Equity',
        'normal_balance' => 'sideways',
    ])->assertSessionHasErrors('normal_balance');
});

test('a user without ledger-accounts.delete cannot delete a ledger account type', function () {
    $type = LedgerAccountType::create(['name' => 'Income', 'normal_balance' => 'credit']);
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->delete("/admin/ledger-account-types/{$type->id}")->assertForbidden();
});

test('a user with ledger-accounts.delete can soft delete a ledger account type', function () {
    $type = LedgerAccountType::create(['name' => 'Income', 'normal_balance' => 'credit']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.delete']);

    $this->actingAs($user)->delete("/admin/ledger-account-types/{$type->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('ledger_account_types', ['id' => $type->id]);
});