<?php

use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);
});

it('has a permission for each transaction action', function () {
    foreach (['view', 'create', 'reverse-request', 'reverse-approve'] as $action) {
        expect(Permission::where('name', "transactions.{$action}")->exists())->toBeTrue();
    }
});

// a farmer keeps their own books, so recording is theirs by default
it('lets a farmer view and record', function () {
    $farmer = Role::findByName('farmer');

    expect($farmer->hasPermissionTo('transactions.view'))->toBeTrue();
    expect($farmer->hasPermissionTo('transactions.create'))->toBeTrue();
});

it('does not let a farmer cancel anything', function () {
    $farmer = Role::findByName('farmer');

    expect($farmer->hasPermissionTo('transactions.reverse-request'))->toBeFalse();
    expect($farmer->hasPermissionTo('transactions.reverse-approve'))->toBeFalse();
});

it('lets an agent record and ask for a cancellation', function () {
    $agent = Role::findByName('agent');

    expect($agent->hasPermissionTo('transactions.create'))->toBeTrue();
    expect($agent->hasPermissionTo('transactions.reverse-request'))->toBeTrue();
});

// whoever asks cannot be whoever agrees, so an agent never holds both
it('does not let an agent approve a cancellation', function () {
    expect(Role::findByName('agent')->hasPermissionTo('transactions.reverse-approve'))->toBeFalse();
});

it('lets an admin approve a cancellation', function () {
    expect(Role::findByName('admin')->hasPermissionTo('transactions.reverse-approve'))->toBeTrue();
});
