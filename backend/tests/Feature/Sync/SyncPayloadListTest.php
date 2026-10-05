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

    $this->agent = User::factory()->create();
    $this->agent->assignRole('agent');
    $this->otherAgent = User::factory()->create();
    $this->otherAgent->assignRole('agent');

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id, 'assigned_agent_id' => $this->agent->id]);
    $this->unit = FarmUnit::factory()->create(['farmer_profile_id' => $this->profile->id, 'approved_at' => now()->subMonth(), 'approved_by' => $this->agent->id]);

    $this->otherFarmerUser = User::factory()->create();
    $this->otherFarmerUser->assignRole('farmer');
    $this->otherProfile = FarmerProfile::factory()->create(['user_id' => $this->otherFarmerUser->id, 'assigned_agent_id' => $this->otherAgent->id]);
    $this->otherUnit = FarmUnit::factory()->create(['farmer_profile_id' => $this->otherProfile->id, 'approved_at' => now()->subMonth(), 'approved_by' => $this->otherAgent->id]);
});

function payloadRec(array $o = []): array
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

// a sale bigger than the stock, so it comes back needs_fixing
function fixable(?FarmUnit $unit = null, ?FarmerProfile $profile = null, array $o = []): array
{
    $unit ??= test()->unit;
    $profile ??= test()->profile;
    FarmUnitStock::factory()->create(['farm_unit_id' => $unit->id, 'opening_quantity' => 10]);

    return payloadRec($o + ['template' => test()->produce->id, 'farmer' => $profile->uuid, 'farm_unit_id' => $unit->id, 'quantity' => '1000']);
}

function send(User $user, array ...$records)
{
    return test()->actingAs($user)->postJson('/sync/submissions', ['records' => $records]);
}

function listed(User $user, string $uuid): ?array
{
    return collect(test()->actingAs($user)->getJson('/sync/submissions')->json('data'))->firstWhere('uuid', $uuid);
}

test('a needs_fixing row carries exactly what the device sent', function () {
    $sent = fixable(o: ['narration' => 'Sold at the market', 'is_credit' => false, 'event_date' => '2026-03-02']);
    expect(send($this->farmerUser, $sent)->json('results.0.status'))->toBe('needs_fixing');

    expect(listed($this->farmerUser, $sent['uuid'])['payload'])->toEqual($sent);
});

test('other statuses have no payload key', function () {
    $accepted = payloadRec();
    $held = payloadRec(['farmer' => $this->otherProfile->uuid]);
    $rejected = payloadRec();
    $superseded = payloadRec();
    send($this->farmerUser, $accepted, $held, $rejected, $superseded);
    SyncSubmission::where('client_uuid', $rejected['uuid'])->update(['status' => SyncSubmission::REJECTED]);
    SyncSubmission::where('client_uuid', $superseded['uuid'])->update(['status' => SyncSubmission::SUPERSEDED]);

    foreach ([[$accepted, 'accepted'], [$held, 'held_for_review'], [$rejected, 'rejected'], [$superseded, 'superseded']] as [$record, $status]) {
        $row = listed($this->farmerUser, $record['uuid']);
        expect($row['status'])->toBe($status)->and($row)->not->toHaveKey('payload');
    }
});

test('a farmer never sees another farmers payload', function () {
    $theirs = fixable($this->otherUnit, $this->otherProfile);
    send($this->otherFarmerUser, $theirs);

    expect(listed($this->farmerUser, $theirs['uuid']))->toBeNull()
        ->and(listed($this->otherFarmerUser, $theirs['uuid']))->toHaveKey('payload');
});

test('an agent sees payloads for their farmers and their own, not for others', function () {
    $farmersOwn = fixable();
    send($this->farmerUser, $farmersOwn);
    $agentsOwn = fixable();
    send($this->agent, $agentsOwn);
    $elsewhere = fixable($this->otherUnit, $this->otherProfile);
    send($this->otherAgent, $elsewhere);

    expect(listed($this->agent, $farmersOwn['uuid'])['payload'])->toEqual($farmersOwn)
        ->and(listed($this->agent, $agentsOwn['uuid'])['payload'])->toEqual($agentsOwn)
        ->and(listed($this->agent, $elsewhere['uuid']))->toBeNull();
});

test('the payload holds only keys the device sent', function () {
    $sent = fixable();
    send($this->farmerUser, $sent);

    expect(array_diff(array_keys(listed($this->farmerUser, $sent['uuid'])['payload']), array_keys($sent)))->toBe([]);
});

test('every existing key on every row is unchanged', function () {
    $fixing = fixable();
    $accepted = payloadRec();
    send($this->farmerUser, $fixing, $accepted);

    $row = listed($this->farmerUser, $fixing['uuid']);
    $plain = listed($this->farmerUser, $accepted['uuid']);

    expect(array_keys($plain))->toBe(['uuid', 'status', 'reason', 'reference', 'transaction_uuid', 'device_date', 'received_at'])
        ->and(array_keys($row))->toBe(['uuid', 'status', 'reason', 'reference', 'device_date', 'received_at', 'payload'])
        ->and($row['status'])->toBe('needs_fixing')
        ->and($row['reason'])->not->toBeNull()
        ->and($row['reference'])->toBeNull()
        ->and($row['device_date'])->toBe($fixing['event_date']);
});

test('the number of queries does not grow with the rows', function () {
    send($this->farmerUser, fixable(), payloadRec());
    $count = function () {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->farmerUser)->getJson('/sync/submissions')->assertOk();

        return count(DB::getQueryLog());
    };

    $one = $count();

    foreach (range(1, 5) as $_) {
        send($this->farmerUser, fixable(), payloadRec());
    }

    expect($count())->toBe($one);
});
