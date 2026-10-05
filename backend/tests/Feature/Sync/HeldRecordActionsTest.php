<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Models\UserPermissionDenial;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
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

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    // an inactive account's record lands held
    $this->farmerUser = User::factory()->create(['is_active' => false]);
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function heldUuid(): string
{
    test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'uuid' => (string) Str::uuid(), 'template' => test()->template->id, 'farmer' => test()->profile->uuid, 'amount' => '100',
        'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk();

    return SyncSubmission::latest('id')->first()->uuid;
}

function adminDenied(string ...$permissions): User
{
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    foreach ($permissions as $name) {
        UserPermissionDenial::create(['user_id' => $admin->id, 'denied_by' => test()->admin->id, 'permission_id' => Permission::where('name', $name)->value('id')]);
    }

    return $admin;
}

function ajax(User $user)
{
    return test()->actingAs($user)->withHeaders(['X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'application/json']);
}

test('the page tells the admin which actions they hold', function (array $denied, bool $approve, bool $reject) {
    $admin = adminDenied(...$denied);

    $this->actingAs($admin)->get('/admin/sync-submissions')->assertOk()
        ->assertInertia(fn($page) => $page->where('permissions.approve', $approve)->where('permissions.reject', $reject));
})->with([
    'both' => [[], true, true],
    'view only' => [['sync-submissions.approve', 'sync-submissions.reject'], false, false],
    'approve only' => [['sync-submissions.reject'], true, false],
]);

test('approve and reject answer JSON as before when called by AJAX', function () {
    $a = heldUuid();
    $b = heldUuid();

    $approved = ajax($this->admin)->postJson("/admin/sync-submissions/{$a}/approve")->assertOk()->json();
    $rejected = ajax($this->admin)->postJson("/admin/sync-submissions/{$b}/reject", ['reason' => 'No.'])->assertOk()->json();

    expect(array_keys($approved))->toBe(['uuid', 'status', 'reason', 'reference'])
        ->and($approved['status'])->toBe('accepted')
        ->and($approved['reference'])->not->toBeNull()
        ->and($rejected['status'])->toBe('rejected')
        ->and($rejected['reason'])->toBe('No.');
});

test('a second approve of the same row is the existing 422 and posts nothing more', function () {
    $uuid = heldUuid();

    ajax($this->admin)->postJson("/admin/sync-submissions/{$uuid}/approve")->assertOk();
    ajax($this->admin)->postJson("/admin/sync-submissions/{$uuid}/approve")->assertStatus(422)->assertJsonPath('message', 'This record has already been decided.');

    expect(Transaction::count())->toBe(1);
});

test('the page props still hold no numeric id', function () {
    heldUuid();

    $props = Arr::only($this->actingAs($this->admin)->get('/admin/sync-submissions')->viewData('page')['props'], ['submissions', 'permissions']);

    $ids = [];
    array_walk_recursive($props, function ($value, $key) use (&$ids) {
        if (preg_match('/(^|_)id$/', (string) $key)) {
            $ids[] = $key;
        }
    });

    expect($ids)->toBe([]);
});
