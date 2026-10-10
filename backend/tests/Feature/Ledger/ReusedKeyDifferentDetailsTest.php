<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\LedgerAccount;
use App\Models\LedgerCategory;
use App\Models\LedgerClass;
use App\Models\LedgerControl;
use App\Models\LedgerSubcategory;
use App\Models\LedgerType;
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\SyncSubmissionService;
use Database\Seeders\PermissionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

const REUSED = 'This record was already saved with different details.';

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
    $make = fn(string $slug) => TransactionTemplate::create([
        'name' => $slug, 'slug' => $slug, 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    $this->sale = $make('crop_sale');
    $this->otherSale = $make('other_sale');
    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function keyedWeb(string $key, array $o = [])
{
    return test()->actingAs(test()->farmerUser)->postJson('/my-records', $o + [
        'transaction_template_id' => test()->sale->id,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'transaction_date' => now()->toDateString(),
        'idempotency_key' => $key,
    ]);
}

function keyedRecord(string $uuid, array $o = []): array
{
    return $o + [
        'uuid' => $uuid,
        'template' => test()->sale->id,
        'farmer' => test()->profile->uuid,
        'amount' => '100',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ];
}

function keyedSync(array $record): array
{
    return test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk()->json('results.0');
}

test('web: the same key with a different amount is refused and the original is untouched', function () {
    $key = (string) Str::uuid();
    $ref = keyedWeb($key)->assertOk()->json('reference');

    keyedWeb($key, ['amount' => '200'])->assertStatus(422)->assertExactJson(['message' => REUSED]);

    $only = Transaction::first();

    expect(Transaction::count())->toBe(1)->and($only->reference)->toBe($ref)->and($only->amount_minor)->toBe(10000);
});

test('web: the same key with a different template is refused the same way', function () {
    $key = (string) Str::uuid();
    keyedWeb($key)->assertOk();

    keyedWeb($key, ['transaction_template_id' => $this->otherSale->id])->assertStatus(422)->assertExactJson(['message' => REUSED]);

    expect(Transaction::count())->toBe(1)->and(Transaction::first()->transaction_template_id)->toBe($this->sale->id);
});

test('web: the same template and amount on another date returns the existing transaction', function () {
    $key = (string) Str::uuid();
    $ref = keyedWeb($key)->assertOk()->json('reference');

    expect(keyedWeb($key, ['transaction_date' => now()->subDay()->toDateString()])->assertOk()->json('reference'))->toBe($ref)
        ->and(Transaction::count())->toBe(1);
});

test('web: an identical retry returns the existing transaction', function () {
    $key = (string) Str::uuid();
    $ref = keyedWeb($key)->assertOk()->json('reference');

    expect(keyedWeb($key)->assertOk()->json('reference'))->toBe($ref)->and(Transaction::count())->toBe(1);
});

test('web: 100 then 100.00 on the same key is not a mismatch', function () {
    $key = (string) Str::uuid();
    $ref = keyedWeb($key, ['amount' => '100'])->assertOk()->json('reference');

    expect(keyedWeb($key, ['amount' => '100.00'])->assertOk()->json('reference'))->toBe($ref)->and(Transaction::count())->toBe(1);
});

test('sync: the same uuid with a different amount is an error and changes nothing', function () {
    $uuid = (string) Str::uuid();
    $first = keyedSync(keyedRecord($uuid));
    $row = SyncSubmission::first()->only(['status', 'transaction_id', 'payload']);

    $result = keyedSync(keyedRecord($uuid, ['amount' => '200']));

    expect($result)->toBe(['uuid' => $uuid, 'status' => 'error', 'error' => REUSED])
        ->and(SyncSubmission::count())->toBe(1)
        ->and(SyncSubmission::first()->only(['status', 'transaction_id', 'payload']))->toBe($row)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::first()->amount_minor)->toBe(10000)
        ->and($first['status'])->toBe('accepted')
        ->and(Notification::count())->toBe(0);
});

test('sync: the same uuid with a different template is an error the same way', function () {
    $uuid = (string) Str::uuid();
    keyedSync(keyedRecord($uuid));

    $result = keyedSync(keyedRecord($uuid, ['template' => $this->otherSale->id]));

    expect($result)->toBe(['uuid' => $uuid, 'status' => 'error', 'error' => REUSED])
        ->and(SyncSubmission::count())->toBe(1)->and(Transaction::count())->toBe(1);
});

test('sync: an identical retry returns the stored result', function () {
    $uuid = (string) Str::uuid();
    $first = keyedSync(keyedRecord($uuid));

    expect(keyedSync(keyedRecord($uuid, ['amount' => '100.00'])))->toBe($first)->and(Transaction::count())->toBe(1);
});

test('sync: a racing twin with different content gives the error, not the twin result', function () {
    $uuid = (string) Str::uuid();
    $twin = keyedRecord($uuid);
    $mine = keyedRecord($uuid, ['amount' => '200']);
    $active = true;
    $raced = false;

    // after the "already stored?" lookup, a twin with other details finishes (the race, simulated)
    DB::listen(function ($query) use (&$active, &$raced, $uuid, $twin) {
        if ($active && ! $raced && str_contains($query->sql, 'from "sync_submissions" where "client_uuid"') && in_array($uuid, $query->bindings, true)) {
            $raced = true;
            app(SyncSubmissionService::class)->submit($this->farmerUser, $twin);
        }
    });

    try {
        $result = keyedSync($mine);
    } finally {
        $active = false;
    }

    expect($result)->toBe(['uuid' => $uuid, 'status' => 'error', 'error' => REUSED])
        ->and(SyncSubmission::count())->toBe(1)
        ->and(Transaction::count())->toBe(1)
        ->and(Transaction::first()->amount_minor)->toBe(10000);
});
