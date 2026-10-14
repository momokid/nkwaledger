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
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
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

    $farmer = function (array $user = []) {
        $account = User::factory()->create($user);
        $account->assignRole('farmer');

        return [$account, FarmerProfile::factory()->create(['user_id' => $account->id])];
    };

    [$this->userA, $this->profileA] = $farmer();
    [$this->userB, $this->profileB] = $farmer();
    $this->farmer = $farmer;
});

function webPost(User $user, string $key)
{
    return test()->actingAs($user)->postJson('/my-records', [
        'transaction_template_id' => test()->template->id,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'transaction_date' => now()->toDateString(),
        'idempotency_key' => $key,
    ]);
}

function syncPost(User $user, FarmerProfile $profile, string $uuid)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => [[
        'uuid' => $uuid,
        'template' => test()->template->id,
        'farmer' => $profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ]]])->assertOk()->json('results.0');
}

test('another user\'s web key used as a sync uuid posts a new transaction under the scoped key', function () {
    $key = (string) Str::uuid();
    $aRef = webPost($this->userA, $key)->assertOk()->json('reference');

    $result = syncPost($this->userB, $this->profileB, $key);

    $mine = Transaction::where('farmer_profile_id', $this->profileB->id)->first();

    expect($result['status'])->toBe('accepted')
        ->and($result['reference'])->toBe($mine->reference)->and($result['reference'])->not->toBe($aRef)
        ->and($mine->idempotency_key)->toBe("sync.{$this->userB->id}.{$key}")
        ->and(Transaction::where('reference', $aRef)->first()->idempotency_key)->toBe($key)
        ->and(Transaction::count())->toBe(2);
});

test('a web key that exists on another farmer\'s transaction is refused with the generic text', function () {
    $key = (string) Str::uuid();
    $aRef = webPost($this->userA, $key)->assertOk()->json('reference');

    $response = webPost($this->userB, $key)->assertStatus(422)->assertExactJson(['message' => 'Something went wrong. Please try again.']);

    expect($response->getContent())->not->toContain($aRef)->and(Transaction::count())->toBe(1);
});

test('the same user retrying the same web key for the same farmer gets the existing transaction', function () {
    $key = (string) Str::uuid();

    $first = webPost($this->userA, $key)->assertOk()->json('reference');
    $second = webPost($this->userA, $key)->assertOk()->json('reference');

    expect($second)->toBe($first)->and(Transaction::count())->toBe(1);
});

test('a sync retry of the same uuid by the same user returns the stored result and posts once', function () {
    $uuid = (string) Str::uuid();

    $first = syncPost($this->userA, $this->profileA, $uuid);
    $second = syncPost($this->userA, $this->profileA, $uuid);

    expect($second)->toBe($first)->and(Transaction::count())->toBe(1);
});

test('a retry of an accepted sync record whose transaction has an old raw key still returns the stored result', function () {
    $uuid = (string) Str::uuid();
    $first = syncPost($this->userA, $this->profileA, $uuid);

    DB::table('transactions')->update(['idempotency_key' => $uuid]);

    expect(syncPost($this->userA, $this->profileA, $uuid))->toBe($first)->and(Transaction::count())->toBe(1);
});

test('the scoped key for the highest possible user id fits the 64 character column', function () {
    [$user, $profile] = ($this->farmer)(['id' => PHP_INT_MAX]);
    $uuid = (string) Str::uuid();

    syncPost($user, $profile, $uuid);

    $key = Transaction::first()->idempotency_key;

    expect($key)->toBe('sync.' . PHP_INT_MAX . '.' . $uuid)->and(strlen($key))->toBeLessThanOrEqual(64);
});
