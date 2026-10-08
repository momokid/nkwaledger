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
use App\Models\Notification;
use App\Models\SyncSubmission;
use App\Models\Transaction;
use App\Models\TransactionTemplate;
use App\Models\User;
use App\Services\Ledger\PostingService;
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
    $this->sales = LedgerAccount::create(['name' => 'Sales', 'control_id' => $control->id, 'subcategory_id' => $incomeSub->id, 'type_id' => $type->id]);

    $this->saleTemplate = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->sales->id, 'settlement_side' => 'debit',
    ]);
    $this->produceTemplate = TransactionTemplate::create([
        'name' => 'I sold produce', 'slug' => 'produce_sale_tracked', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->sales->id, 'settlement_side' => 'debit',
        'requires_farm_unit' => true, 'is_produce_sale' => true,
    ]);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);

    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'approved_at' => now()->subMonth(), 'approved_by' => $this->agent->id]);
});

function rec(array $o = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => test()->saleTemplate->id,
        'farmer' => test()->profile->uuid,
        'amount' => '250.75',
        'settlement_account_id' => test()->cash->id,
        'event_date' => now()->subDay()->toDateString(),
        'device_created_at' => now()->subHour()->toIso8601String(),
    ], $o);
}

function oversold(array $o = []): array
{
    FarmUnitStock::factory()->create(['farm_unit_id' => test()->unit->id, 'opening_quantity' => 10]);

    return rec(array_merge(['template' => test()->produceTemplate->id, 'farm_unit_id' => test()->unit->id, 'quantity' => '20'], $o));
}

function sync(User $user, array ...$records)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records]);
}

test('a duplicate uuid posts once and returns the stored result', function () {
    $record = rec();

    $first = sync($this->farmerUser, $record)->assertOk()->json('results.0');
    $second = sync($this->farmerUser, $record)->assertOk()->json('results.0');

    expect($first['status'])->toBe('accepted')
        ->and($second)->toBe($first)
        ->and(Transaction::count())->toBe(1)
        ->and(SyncSubmission::count())->toBe(1);
});

test('the device date and received_at are both stored', function () {
    $date = now()->subDays(3)->toDateString();
    sync($this->farmerUser, rec(['event_date' => $date]));

    $row = SyncSubmission::first();

    expect($row->device_date->toDateString())->toBe($date)
        ->and($row->received_at)->not->toBeNull()
        ->and(Transaction::first()->transaction_date->toDateString())->toBe($date);
});

test('a sale bigger than the stock needs fixing, posts nothing and tells the right people', function () {
    $response = sync($this->farmerUser, oversold())->assertOk();

    expect($response->json('results.0.status'))->toBe('needs_fixing')
        ->and($response->json('results.0.reason'))->toBe('That is more than the farm has on record. Please check the number.')
        ->and(Transaction::count())->toBe(0)
        ->and(Notification::where('user_id', $this->farmerUser->id)->count())->toBe(1)
        ->and(Notification::where('user_id', $this->agent->id)->count())->toBe(1);
});

test('when an agent submitted it only the agent is told', function () {
    sync($this->agent, oversold())->assertOk();

    expect(Notification::where('user_id', $this->agent->id)->count())->toBe(1)
        ->and(Notification::where('user_id', $this->farmerUser->id)->count())->toBe(0);
});

test('a batch keeps its order and one failure does not block the rest', function () {
    $a = rec();
    $b = oversold();
    $c = rec();

    $results = sync($this->farmerUser, $a, $b, $c)->assertOk()->json('results');

    expect(array_column($results, 'uuid'))->toBe([$a['uuid'], $b['uuid'], $c['uuid']])
        ->and(array_column($results, 'status'))->toBe(['accepted', 'needs_fixing', 'accepted'])
        ->and(Transaction::count())->toBe(2);
});

test('a suspended user is held for review and posts nothing', function () {
    $this->farmerUser->update(['is_active' => false]);

    $result = sync($this->farmerUser, rec())->assertOk()->json('results.0');

    expect($result['status'])->toBe('held_for_review')
        ->and(Transaction::count())->toBe(0);
});

test('a user whose recording permission was revoked is held for review', function () {
    $this->farmerUser->removeRole('farmer');

    expect(sync($this->farmerUser, rec())->json('results.0.status'))->toBe('held_for_review')
        ->and(Transaction::count())->toBe(0);
});

test('an agent acting for a farmer who is not theirs is held for review', function () {
    $result = sync($this->otherAgent, rec())->assertOk()->json('results.0');

    expect($result['status'])->toBe('held_for_review')
        ->and(Transaction::count())->toBe(0);
});

test('a fix with supersedes marks the old one superseded once accepted', function () {
    $bad = oversold();
    sync($this->farmerUser, $bad);

    $fix = rec(['supersedes' => $bad['uuid']]);
    expect(sync($this->farmerUser, $fix)->json('results.0.status'))->toBe('accepted');

    $old = SyncSubmission::where('client_uuid', $bad['uuid'])->first();

    expect($old->status)->toBe('superseded')
        ->and(SyncSubmission::where('client_uuid', $fix['uuid'])->first()->supersedes_id)->toBe($old->id);
});

test('a fix that is itself refused leaves the old one as it was', function () {
    $bad = oversold();
    sync($this->farmerUser, $bad);

    sync($this->farmerUser, oversold(['supersedes' => $bad['uuid'], 'quantity' => '99']));

    expect(SyncSubmission::where('client_uuid', $bad['uuid'])->first()->status)->toBe('needs_fixing');
});

test('a template or settlement account the farmer may not use needs fixing', function () {
    $adjustment = TransactionTemplate::create(['name' => 'Fix', 'slug' => 'correction', 'transaction_type' => 'ADJUSTMENT', 'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->sales->id, 'settlement_side' => 'none']);

    $results = sync($this->farmerUser, rec(['settlement_account_id' => $this->sales->id]), rec(['template' => $adjustment->id]))->json('results');

    expect(array_column($results, 'status'))->toBe(['needs_fixing', 'needs_fixing'])
        ->and(Transaction::count())->toBe(0);
});

test('an unexpected error stores nothing, so a retry is safe', function () {
    $this->mock(PostingService::class)->shouldReceive('post')->once()->andThrow(new RuntimeException('boom'));

    $result = sync($this->farmerUser, rec())->assertOk()->json('results.0');

    expect($result)->toHaveKey('error')->and(SyncSubmission::count())->toBe(0)->and(Transaction::count())->toBe(0);
});

test('more than 50 records is refused', function () {
    $this->actingAs($this->farmerUser)
        ->postJson('/sync/submissions', ['records' => array_map(fn() => rec(), range(1, 51))])
        ->assertStatus(422);
});

test('a guest cannot sync', function () {
    $this->postJson('/sync/submissions', ['records' => [rec()]])->assertUnauthorized();
});

test('a farmer lists their own submissions, an agent also their farmers', function () {
    sync($this->farmerUser, rec());
    sync($this->otherAgent, rec());

    expect($this->actingAs($this->farmerUser)->getJson('/sync/submissions')->json('data'))->toHaveCount(2);
    expect($this->actingAs($this->agent)->getJson('/sync/submissions')->json('data'))->toHaveCount(2);

    $stranger = User::factory()->create();
    $stranger->assignRole('farmer');
    FarmerProfile::factory()->create(['user_id' => $stranger->id]);

    expect($this->actingAs($stranger)->getJson('/sync/submissions')->json('data'))->toHaveCount(0);
});

test('listing a farmer out of scope is a 404', function () {
    $this->actingAs($this->otherAgent)->getJson("/sync/submissions?farmer={$this->profile->uuid}")->assertNotFound();
    $this->actingAs($this->agent)->getJson("/sync/submissions?farmer={$this->profile->uuid}")->assertOk();
});
