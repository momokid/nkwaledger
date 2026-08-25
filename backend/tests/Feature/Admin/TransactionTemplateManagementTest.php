<?php

use App\Models\FarmTypeCategory;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\TransactionTemplate;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->givePermissionTo([
        'transaction-templates.view',
        'transaction-templates.create',
        'transaction-templates.update',
        'transaction-templates.delete',
    ]);

    $drClass = LedgerClass::create(['name' => 'Dr']);
    $crClass = LedgerClass::create(['name' => 'Cr']);

    $assetsCategory = LedgerCategory::create(['name' => 'Assets', 'class_id' => $drClass->id]);
    $incomeCategory = LedgerCategory::create(['name' => 'Income', 'class_id' => $crClass->id]);

    $assetSubcategory = LedgerSubcategory::create([
        'category_id' => $assetsCategory->id,
        'name' => 'Short Term Asset',
    ]);

    $incomeSubcategory = LedgerSubcategory::create([
        'category_id' => $incomeCategory->id,
        'name' => 'Farm Income',
    ]);

    $control = LedgerControl::create(['name' => 'Cash Ctrl']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cashAccount = LedgerAccount::create([
        'name' => 'Cash on Hand',
        'control_id' => $control->id,
        'subcategory_id' => $assetSubcategory->id,
        'type_id' => $type->id,
    ]);

    $this->salesAccount = LedgerAccount::create([
        'name' => 'Crop Sales',
        'control_id' => $control->id,
        'subcategory_id' => $incomeSubcategory->id,
        'type_id' => $type->id,
    ]);

    $this->validPayload = [
        'name' => 'I sold crops',
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cashAccount->id,
        'credit_account_id' => $this->salesAccount->id,
        'settlement_side' => 'debit',
        'requires_farm_unit' => false,
    ];
});

it('lists transaction templates for a user with view permission', function () {
    TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($this->admin)->get(route('admin.transaction-templates.index'));

    $response->assertOk();
    $response->assertInertia(
        fn($page) => $page
            ->component('Admin/TransactionTemplates/Index')
            ->has('transactionTemplates.data', 1)
    );
});

it('denies listing to a user without view permission', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('admin.transaction-templates.index'));

    $response->assertForbidden();
});

it('creates a transaction template with valid data', function () {
    $response = $this->actingAs($this->admin)
        ->post(route('admin.transaction-templates.store'), $this->validPayload);

    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_templates', [
        'slug' => 'crop_sale',
        'transaction_type' => 'INCOME',
    ]);
});

it('creates a transaction template scoped to a farm type category', function () {
    $category = FarmTypeCategory::create(['name' => 'Livestock']);

    $response = $this->actingAs($this->admin)->post(route('admin.transaction-templates.store'), [
        ...$this->validPayload,
        'farm_type_category_id' => $category->id,
    ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_templates', [
        'slug' => 'crop_sale',
        'farm_type_category_id' => $category->id,
    ]);
});

it('rejects a duplicate slug on create', function () {
    TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($this->admin)->post(route('admin.transaction-templates.store'), [
        ...$this->validPayload,
        'name' => 'I sold maize',
    ]);

    $response->assertSessionHasErrors('slug');
});

it('rejects the same account on both sides on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.transaction-templates.store'), [
        ...$this->validPayload,
        'credit_account_id' => $this->cashAccount->id,
    ]);

    $response->assertSessionHasErrors('credit_account_id');
    $this->assertDatabaseMissing('transaction_templates', ['slug' => 'crop_sale']);
});

it('rejects an unknown transaction type on create', function () {
    $response = $this->actingAs($this->admin)->post(route('admin.transaction-templates.store'), [
        ...$this->validPayload,
        'transaction_type' => 'TRANSFER',
    ]);

    $response->assertSessionHasErrors('transaction_type');
});

it('rejects an inactive ledger account on create', function () {
    $this->salesAccount->update(['is_active' => false]);

    $response = $this->actingAs($this->admin)
        ->post(route('admin.transaction-templates.store'), $this->validPayload);

    $response->assertSessionHasErrors('credit_account_id');
});

it('denies creating to a user without create permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('transaction-templates.view');

    $response = $this->actingAs($user)
        ->post(route('admin.transaction-templates.store'), $this->validPayload);

    $response->assertForbidden();
    $this->assertDatabaseMissing('transaction_templates', ['slug' => 'crop_sale']);
});

it('updates a transaction template with valid data', function () {
    $template = TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.transaction-templates.update', $template), [
            ...$this->validPayload,
            'name' => 'I sold my harvest',
        ]);

    $response->assertRedirect();
    $this->assertDatabaseHas('transaction_templates', [
        'id' => $template->id,
        'name' => 'I sold my harvest',
    ]);
});

it('allows updating a template without changing its slug', function () {
    $template = TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.transaction-templates.update', $template), $this->validPayload);

    $response->assertRedirect();
    $response->assertSessionHasNoErrors();
});

it('rejects a duplicate slug on update', function () {
    TransactionTemplate::create($this->validPayload);
    $template = TransactionTemplate::create([
        ...$this->validPayload,
        'name' => 'I bought feed',
        'slug' => 'feed_purchase',
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.transaction-templates.update', $template), [
            ...$this->validPayload,
            'slug' => 'crop_sale',
        ]);

    $response->assertSessionHasErrors('slug');
});

it('refuses to update a system template', function () {
    $template = TransactionTemplate::create([
        ...$this->validPayload,
        'is_system' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->put(route('admin.transaction-templates.update', $template), [
            ...$this->validPayload,
            'name' => 'Renamed',
        ]);

    $response->assertSessionHasErrors();
    $this->assertDatabaseHas('transaction_templates', [
        'id' => $template->id,
        'name' => 'I sold crops',
    ]);
});

it('soft deletes a transaction template', function () {
    $template = TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.transaction-templates.destroy', $template));

    $response->assertRedirect();
    $this->assertSoftDeleted('transaction_templates', ['id' => $template->id]);
});

it('refuses to delete a system template', function () {
    $template = TransactionTemplate::create([
        ...$this->validPayload,
        'is_system' => true,
    ]);

    $response = $this->actingAs($this->admin)
        ->delete(route('admin.transaction-templates.destroy', $template));

    $response->assertSessionHasErrors();
    $this->assertDatabaseHas('transaction_templates', ['id' => $template->id, 'deleted_at' => null]);
});

it('denies deleting to a user without delete permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['transaction-templates.view', 'transaction-templates.create', 'transaction-templates.update']);
    $template = TransactionTemplate::create($this->validPayload);

    $response = $this->actingAs($user)
        ->delete(route('admin.transaction-templates.destroy', $template));

    $response->assertForbidden();
    $this->assertDatabaseHas('transaction_templates', ['id' => $template->id, 'deleted_at' => null]);
});
