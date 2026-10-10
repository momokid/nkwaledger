<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $this->cash = LedgerAccount::create(['name' => 'Cash', 'control_id' => $control->id, 'subcategory_id' => $assetSub->id, 'type_id' => $type->id, 'is_settlement' => true]);
    $sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);
    $this->template = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);

    // the three ways in: the farmer's own page, the agent's page for the farmer, the admin's
    $this->pages = [
        'farmer' => fn() => [$this->farmerUser, '/my-records'],
        'agent' => fn() => [$this->agent, "/agent/farmers/{$this->profile->uuid}/records"],
        'admin' => fn() => [$this->admin, "/admin/farmers/{$this->profile->uuid}/records"],
    ];
});

function viewOnly(User $user): User
{
    UserPermissionDenial::create([
        'user_id' => $user->id,
        'permission_id' => Permission::where('name', 'transactions.create')->value('id'),
        'denied_by' => $user->id,
        'reason' => 'test',
    ]);

    return $user;
}

it('tells the page the user may settle when they hold transactions.create', function (string $who) {
    [$user, $url] = ($this->pages[$who])();

    test()->actingAs($user)->get($url)->assertOk()->assertInertia(fn($page) => $page->where('canSettle', true));
})->with(['farmer', 'agent', 'admin']);

it('tells the page the user may not settle when they only have transactions.view', function (string $who) {
    [$user, $url] = ($this->pages[$who])();
    viewOnly($user);

    test()->actingAs($user)->get($url)->assertOk()->assertInertia(fn($page) => $page->where('canSettle', false));
})->with(['farmer', 'agent', 'admin']);

it('still refuses the settle route with a 403 for a user without transactions.create', function () {
    test()->actingAs($this->farmerUser)->post('/my-records', [
        'transaction_template_id' => $this->template->id,
        'amount' => '100',
        'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(),
    ]);
    $transaction = Transaction::firstOrFail();
    viewOnly($this->farmerUser);

    test()->actingAs($this->farmerUser)->post("/my-records/{$transaction->uuid}/settle", [
        'amount' => '10',
        'settlement_account_id' => $this->cash->id,
    ])->assertForbidden();
});
