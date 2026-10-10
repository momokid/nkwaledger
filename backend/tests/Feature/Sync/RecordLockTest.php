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
use App\Services\RecordLock;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;

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

    $this->userA = User::factory()->create();
    $this->userA->assignRole('farmer');
    $this->profileA = FarmerProfile::factory()->create(['user_id' => $this->userA->id]);
    $this->userB = User::factory()->create();
    $this->userB->assignRole('farmer');
    $this->profileB = FarmerProfile::factory()->create(['user_id' => $this->userB->id]);
});

function lockWeb(User $user, string $key, string $amount = '100')
{
    return test()->actingAs($user)->postJson('/my-records', [
        'transaction_template_id' => test()->template->id,
        'amount' => $amount,
        'settlement_account_id' => test()->cash->id,
        'transaction_date' => now()->toDateString(),
        'idempotency_key' => $key,
    ]);
}

function lockSync(User $user, FarmerProfile $profile, string $uuid, string $amount = '100'): array
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => [[
        'uuid' => $uuid,
        'template' => test()->template->id,
        'farmer' => $profile->uuid,
        'amount' => $amount,
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk()->json('results.0');
}

test('the web path and the sync path ask for the lock with the same user and uuid', function () {
    $lock = Mockery::spy(RecordLock::class)->makePartial();
    app()->instance(RecordLock::class, $lock);
    $uuid = (string) Str::uuid();

    lockWeb($this->userA, $uuid)->assertOk();
    lockSync($this->userA, $this->profileA, $uuid);

    $lock->shouldHaveReceived('around')->with($this->userA->id, $uuid, Mockery::type(Closure::class))->twice();
});

test('a web post of a record that already arrived through sync posts nothing and answers as a replay', function () {
    $uuid = (string) Str::uuid();
    $synced = lockSync($this->userA, $this->profileA, $uuid);

    $web = lockWeb($this->userA, $uuid)->assertOk();

    expect($web->json('reference'))->toBe($synced['reference'])
        ->and(Transaction::count())->toBe(1);
});

test('a web post of a synced record with different details is refused and posts nothing', function () {
    $uuid = (string) Str::uuid();
    lockSync($this->userA, $this->profileA, $uuid, '100');

    lockWeb($this->userA, $uuid, '250')->assertStatus(422)->assertJson(['message' => Transaction::KEY_REUSED]);

    expect(Transaction::count())->toBe(1);
});

test('another user\'s synced record with the same uuid does not block a web post', function () {
    $uuid = (string) Str::uuid();
    $theirs = lockSync($this->userB, $this->profileB, $uuid);

    $mine = lockWeb($this->userA, $uuid)->assertOk();

    expect($mine->json('reference'))->not->toBe($theirs['reference'])
        ->and(Transaction::count())->toBe(2)
        ->and(Transaction::where('idempotency_key', "sync.{$this->userB->id}.{$uuid}")->count())->toBe(1);
});
