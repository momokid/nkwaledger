<?php

use App\Models\LedgerAccount;
use App\Models\LedgerAccountType;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    foreach (['view', 'create', 'update', 'delete'] as $action) {
        Permission::firstOrCreate(['name' => "ledger-accounts.{$action}", 'guard_name' => 'web']);
    }
});

test('a guest is redirected to login when visiting ledger accounts', function () {
    $this->get('/admin/ledger-accounts')->assertRedirect('/login');
});

test('a user without ledger-accounts.view cannot view the list', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get('/admin/ledger-accounts')->assertForbidden();
});

test('a user with ledger-accounts.view granted directly can view the list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->get('/admin/ledger-accounts')->assertOk();
});

test('an admin who has been explicitly denied ledger-accounts.view cannot view the list', function () {
    $denier = User::factory()->create();
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('ledger-accounts.view');

    UserPermissionDenial::create([
        'user_id' => $admin->id,
        'permission_id' => Permission::where('name', 'ledger-accounts.view')->value('id'),
        'denied_by' => $denier->id,
    ]);

    $this->actingAs($admin)->get('/admin/ledger-accounts')->assertForbidden();
});

test('a user without ledger-accounts.create cannot create a ledger account', function () {
    $type = LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->post('/admin/ledger-accounts', [
        'name' => 'Cash/MoMo',
        'type_id' => $type->id,
    ])->assertForbidden();
});

test('a user with ledger-accounts.create can create a ledger account', function () {
    $type = LedgerAccountType::create(['name' => 'Asset', 'normal_balance' => 'debit']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-accounts', [
        'name' => 'Cash/MoMo',
        'type_id' => $type->id,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('ledger_accounts', [
        'name' => 'Cash/MoMo',
        'type_id' => $type->id,
    ]);
});

test('a ledger account can be created without a type', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-accounts', [
        'name' => 'Uncategorized',
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('ledger_accounts', ['name' => 'Uncategorized', 'type_id' => null]);
});

test('creating a ledger account with a non-existent type_id fails validation', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-accounts', [
        'name' => 'Cash/MoMo',
        'type_id' => 999,
    ])->assertSessionHasErrors('type_id');
});

test('creating a ledger account with a duplicate name fails validation', function () {
    LedgerAccount::create(['name' => 'Cash/MoMo']);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.create']);

    $this->actingAs($user)->post('/admin/ledger-accounts', [
        'name' => 'Cash/MoMo',
    ])->assertSessionHasErrors('name');
});

test('a user without ledger-accounts.update cannot update a ledger account', function () {
    $account = LedgerAccount::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->put("/admin/ledger-accounts/{$account->id}", [
        'name' => 'Updated Name',
    ])->assertForbidden();
});

test('a user with ledger-accounts.update can update a ledger account, including toggling is_active', function () {
    $account = LedgerAccount::factory()->create(['is_active' => true]);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.update']);

    $this->actingAs($user)->put("/admin/ledger-accounts/{$account->id}", [
        'name' => 'Updated Name',
        'is_active' => false,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $this->assertDatabaseHas('ledger_accounts', [
        'id' => $account->id,
        'name' => 'Updated Name',
        'is_active' => false,
    ]);
});

test('a user without ledger-accounts.delete cannot delete a ledger account', function () {
    $account = LedgerAccount::factory()->create();
    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $this->actingAs($user)->delete("/admin/ledger-accounts/{$account->id}")->assertForbidden();
});

test('a user with ledger-accounts.delete can soft delete a non-system ledger account', function () {
    $account = LedgerAccount::factory()->create(['is_system' => false]);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.delete']);

    $this->actingAs($user)->delete("/admin/ledger-accounts/{$account->id}")
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $this->assertSoftDeleted('ledger_accounts', ['id' => $account->id]);
});

test('a system ledger account cannot be deleted even with permission', function () {
    $account = LedgerAccount::factory()->create(['is_system' => true]);
    $user = User::factory()->create();
    $user->givePermissionTo(['ledger-accounts.view', 'ledger-accounts.delete']);

    $this->actingAs($user)->delete("/admin/ledger-accounts/{$account->id}")
        ->assertSessionHasErrors();

    $this->assertDatabaseHas('ledger_accounts', ['id' => $account->id, 'deleted_at' => null]);
});

test('a soft-deleted ledger account is excluded from the default index', function () {
    $active = LedgerAccount::factory()->create(['name' => 'Cash/MoMo']);
    $deleted = LedgerAccount::factory()->create(['name' => 'Old Account']);
    $deleted->delete();

    $user = User::factory()->create();
    $user->givePermissionTo('ledger-accounts.view');

    $response = $this->actingAs($user)->get('/admin/ledger-accounts');

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->has('ledgerAccounts.data', 1)
            ->where('ledgerAccounts.data.0.name', 'Cash/MoMo')
    );
});
