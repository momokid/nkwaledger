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

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function narrationRecord(array $o = []): array
{
    return $o + [
        'uuid' => (string) Str::uuid(), 'template' => test()->template->id, 'farmer' => test()->profile->uuid, 'amount' => '100',
        'settlement_account_id' => test()->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ];
}

function narrationSync(array $record)
{
    return test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => [$record]]);
}

test('a synced record keeps its narration on the transaction', function () {
    narrationSync(narrationRecord(['narration' => 'Sold at Ejura market']))->assertOk();

    expect(Transaction::first()->narration)->toBe('Sold at Ejura market');
});

test('the web route and sync store the same narration, including trimming and an empty string', function (?string $typed, ?string $stored) {
    $this->actingAs($this->farmerUser)->postJson('/my-records', [
        'transaction_template_id' => $this->template->id, 'amount' => '100', 'settlement_account_id' => $this->cash->id,
        'transaction_date' => now()->toDateString(), 'narration' => $typed,
    ])->assertOk();

    narrationSync(narrationRecord(['narration' => $typed]))->assertOk();

    [$web, $sync] = Transaction::orderBy('id')->get()->all();

    expect($web->narration)->toBe($stored)->and($sync->narration)->toBe($web->narration);
})->with([
    'trimmed' => ['  Sold maize  ', 'Sold maize'],
    'empty' => ['', null],
    'only spaces' => ['   ', null],
]);

test('a narration over 255 characters gets the structural 422', function () {
    narrationSync(narrationRecord(['narration' => str_repeat('a', 256)]))->assertStatus(422);
    narrationSync(narrationRecord(['narration' => str_repeat('a', 255)]))->assertOk();

    expect(Transaction::count())->toBe(1);
});

test('a record with no narration posts with null', function () {
    narrationSync(narrationRecord())->assertOk();

    expect(Transaction::first()->narration)->toBeNull();
});

test('the stored submission payload contains the narration', function () {
    narrationSync(narrationRecord(['narration' => 'Sold at Ejura market']))->assertOk();

    expect(SyncSubmission::first()->payload['narration'])->toBe('Sold at Ejura market');
});

test('a retry with a different narration returns the stored result and posts nothing new', function () {
    $uuid = (string) Str::uuid();

    $first = narrationSync(narrationRecord(['uuid' => $uuid, 'narration' => 'First note']))->assertOk()->json('results.0');
    $retry = narrationSync(narrationRecord(['uuid' => $uuid, 'narration' => 'Another note']))->assertOk()->json('results.0');

    expect($retry)->toBe($first)->and(Transaction::count())->toBe(1)->and(Transaction::first()->narration)->toBe('First note');
});
