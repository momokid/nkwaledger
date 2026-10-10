<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmUnit;
use App\Models\FarmUnitStock;
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
use App\Services\SyncSubmissionService;
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

    $this->sale = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    $this->produce = TransactionTemplate::create([
        'name' => 'I sold produce', 'slug' => 'produce_sale_tracked', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
        'requires_farm_unit' => true, 'is_produce_sale' => true,
    ]);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'approved_at' => now()->subMonth(), 'approved_by' => $this->admin->id]);

    $this->otherUser = User::factory()->create();
    $this->otherUser->assignRole('farmer');
    $this->otherProfile = FarmerProfile::factory()->create(['user_id' => $this->otherUser->id]);
});

function uuidRec(array $o = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => test()->sale->id,
        'farmer' => test()->profile->uuid,
        'amount' => '250.75',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->subDay()->toDateString(),
        'device_created_at' => now()->subHour()->toIso8601String(),
    ], $o);
}

// bigger than the stock, so it comes back needs_fixing
function uuidFixable(): array
{
    FarmUnitStock::factory()->create(['farm_unit_id' => test()->unit->id, 'opening_quantity' => 10]);

    return uuidRec(['template' => test()->produce->id, 'farm_unit_id' => test()->unit->id, 'quantity' => '1000']);
}

function uuidSend(User $user, array ...$records)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records])->assertOk()->json('results');
}

function uuidRow(User $user, string $uuid): ?array
{
    return collect(test()->actingAs($user)->getJson('/sync/submissions')->json('data'))->firstWhere('uuid', $uuid);
}

test('an accepted result carries the new transaction uuid', function () {
    $record = uuidRec();
    $result = uuidSend($this->farmerUser, $record)[0];

    expect($result['transaction_uuid'])->toBe(Transaction::first()->uuid)
        ->and(Str::isUuid($result['transaction_uuid']))->toBeTrue();
});

test('a replay returns the same transaction uuid', function () {
    $record = uuidRec();
    $first = uuidSend($this->farmerUser, $record)[0];
    $second = uuidSend($this->farmerUser, $record)[0];

    expect($second['transaction_uuid'])->toBe($first['transaction_uuid'])->and($second)->toBe($first);
});

test('the race replay returns the same transaction uuid', function () {
    $record = uuidRec();
    $competitor = fn() => app(SyncSubmissionService::class)->submit($this->farmerUser, $record);
    $active = true;
    $raced = false;

    DB::listen(function ($query) use (&$active, &$raced, $record, $competitor) {
        if ($active && ! $raced && str_contains($query->sql, 'from "sync_submissions" where "client_uuid"') && in_array($record['uuid'], $query->bindings, true)) {
            $raced = true;
            $competitor();
        }
    });

    $result = uuidSend($this->farmerUser, $record)[0];
    $active = false;

    expect($result['transaction_uuid'])->toBe(Transaction::first()->uuid)->and(Transaction::count())->toBe(1);
});

test('no other result or row has a transaction uuid', function () {
    $fixing = uuidFixable();
    $held = uuidRec(['farmer' => $this->otherProfile->uuid]);
    $rejected = uuidRec();
    $superseded = uuidRec();
    $bad = uuidRec();

    $results = uuidSend($this->farmerUser, $fixing, $held, $rejected, $superseded);
    SyncSubmission::where('client_uuid', $rejected['uuid'])->update(['status' => SyncSubmission::REJECTED, 'transaction_id' => null]);
    SyncSubmission::where('client_uuid', $superseded['uuid'])->update(['status' => SyncSubmission::SUPERSEDED, 'transaction_id' => null]);

    // an error result: same uuid again with other details
    uuidSend($this->farmerUser, $bad);
    $error = uuidSend($this->farmerUser, ['amount' => '999'] + $bad)[0];

    expect($error['status'])->toBe('error')->and($error)->not->toHaveKey('transaction_uuid');

    foreach ([$results[0], $results[1]] as $result) {
        expect($result)->not->toHaveKey('transaction_uuid');
    }

    foreach ([[$fixing, 'needs_fixing'], [$held, 'held_for_review'], [$rejected, 'rejected'], [$superseded, 'superseded']] as [$record, $status]) {
        $row = uuidRow($this->farmerUser, $record['uuid']);
        expect($row['status'])->toBe($status)->and($row)->not->toHaveKey('transaction_uuid');
    }
});

test('the list shows it on accepted rows only', function () {
    $ok = uuidRec();
    $fixing = uuidFixable();
    uuidSend($this->farmerUser, $ok, $fixing);

    expect(uuidRow($this->farmerUser, $ok['uuid'])['transaction_uuid'])->toBe(Transaction::first()->uuid)
        ->and(uuidRow($this->farmerUser, $fixing['uuid']))->not->toHaveKey('transaction_uuid');
});

test('an approved held record shows its transaction uuid from then on', function () {
    $held = uuidRec(['farmer' => $this->otherProfile->uuid]);
    uuidSend($this->farmerUser, $held);

    expect(uuidRow($this->admin, $held['uuid']))->not->toHaveKey('transaction_uuid');

    $this->actingAs($this->admin)->postJson('/admin/sync-submissions/' . SyncSubmission::first()->uuid . '/approve')->assertOk();

    expect(uuidRow($this->admin, $held['uuid'])['transaction_uuid'])->toBe(Transaction::first()->uuid);
});

test('existing keys are unchanged and no numeric id is added', function () {
    $record = uuidRec();
    $post = uuidSend($this->farmerUser, $record)[0];
    $row = uuidRow($this->farmerUser, $record['uuid']);

    expect(array_keys($post))->toBe(['uuid', 'status', 'reason', 'reference', 'transaction_uuid'])
        ->and(array_keys($row))->toBe(['uuid', 'status', 'reason', 'reference', 'transaction_uuid', 'device_date', 'received_at'])
        ->and($row['reference'])->toBe(Transaction::first()->reference)
        ->and($post['reason'])->toBeNull();

    foreach (['transaction_uuid', 'uuid'] as $key) {
        expect(Str::isUuid($row[$key]))->toBeTrue();
    }

    expect(collect($row)->filter(fn($v) => is_int($v)))->toBeEmpty();
});

test('the number of queries does not grow with the rows', function () {
    uuidSend($this->farmerUser, uuidRec(), uuidRec());
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->farmerUser)->getJson('/sync/submissions')->assertOk();

        return count(DB::getQueryLog());
    };

    $two = $count();

    uuidSend($this->farmerUser, ...array_map(fn() => uuidRec(), range(1, 8)));

    expect($count())->toBe($two);
});
