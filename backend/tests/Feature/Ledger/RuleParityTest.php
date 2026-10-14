<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
use App\Models\FarmType;
use App\Models\FarmTypeCategory;
use App\Models\JournalEntry;
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
use Illuminate\Support\Str;

// the template and settlement-account rules must read the same on the web form and on sync

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $this->control = LedgerControl::create(['name' => 'General']);
    $this->glType = LedgerType::create(['name' => 'GL']);
    $this->assetSub = $assetSub;

    $account = fn(string $name, int $sub, array $o = []) => LedgerAccount::create($o + [
        'name' => $name, 'control_id' => $this->control->id, 'subcategory_id' => $sub, 'type_id' => $this->glType->id, 'is_settlement' => false,
    ]);

    $this->cash = $account('Cash', $assetSub->id, ['is_settlement' => true]);
    $this->receivable = $account('Accounts Receivable', $assetSub->id, ['is_settlement' => true]);
    $this->payable = $account('Accounts Payable', $assetSub->id, ['is_settlement' => true]);
    $this->sales = $account('Sales', $incomeSub->id);
    $this->account = $account;

    $crop = FarmTypeCategory::create(['name' => 'Crop']);
    $this->livestock = FarmTypeCategory::create(['name' => 'Livestock']);
    $maize = FarmType::create(['name' => 'Maize', 'category_id' => $crop->id, 'is_active' => true]);

    $template = fn(array $o = []) => TransactionTemplate::create($o + [
        'name' => 'I sold ' . Str::random(4), 'slug' => 'sale_' . Str::random(6), 'transaction_type' => 'INCOME', 'allows_credit' => true,
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $this->sales->id, 'settlement_side' => 'debit',
    ]);
    $this->makeTemplate = $template;
    $this->sale = $template(['farm_type_category_id' => $crop->id]);
    $this->categoryLess = $template();
    $this->noCredit = $template(['allows_credit' => false]);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
    $this->profile->farmTypes()->attach($maize->id);
});

// one case, both paths: same field, same wording, nothing posted
function refusedAlike(array $o, string $field, string $message): void
{
    $web = ['transaction_template_id' => $o['template'], 'amount' => '100', 'transaction_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid()]
        + (isset($o['account']) ? ['settlement_account_id' => $o['account']] : [])
        + (($o['credit'] ?? false) ? ['is_credit' => '1'] : []);

    test()->actingAs(test()->farmerUser)->post('/my-records', $web)->assertSessionHasErrors($field);

    expect(session('errors')->getBag('default')->first($field))->toBe($message);

    $record = ['uuid' => (string) Str::uuid(), 'template' => $o['template'], 'farmer' => test()->profile->uuid, 'amount' => '100', 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String()]
        + (isset($o['account']) ? ['settlement_account_id' => $o['account']] : [])
        + (($o['credit'] ?? false) ? ['is_credit' => true] : []);

    $result = test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk()->json('results.0');

    expect($result['status'])->toBe('needs_fixing')->and($result['reason'])->toBe($message)->and(Transaction::count())->toBe(0);
}

test('an inactive template reads the same on both paths', function () {
    $this->sale->update(['is_active' => false]);

    refusedAlike(['template' => $this->sale->id, 'account' => $this->cash->id], 'transaction_template_id', 'The selected transaction template id is invalid.');
});

test('an adjustment template reads the same on both paths', function () {
    $adjustment = ($this->makeTemplate)(['transaction_type' => 'ADJUSTMENT']);

    refusedAlike(['template' => $adjustment->id, 'account' => $this->cash->id], 'transaction_template_id', 'That kind of record does not match your farm.');
});

test('a template for another farm type reads the same on both paths', function () {
    $other = ($this->makeTemplate)(['farm_type_category_id' => $this->livestock->id]);

    refusedAlike(['template' => $other->id, 'account' => $this->cash->id], 'transaction_template_id', 'That kind of record does not match your farm.');
});

test('a category-less template is accepted on both paths', function () {
    $this->actingAs($this->farmerUser)->post('/my-records', ['transaction_template_id' => $this->categoryLess->id, 'amount' => '100', 'settlement_account_id' => $this->cash->id, 'transaction_date' => now()->toDateString()])->assertSessionDoesntHaveErrors();

    $result = $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'uuid' => (string) Str::uuid(), 'template' => $this->categoryLess->id, 'farmer' => $this->profile->uuid, 'amount' => '100',
        'settlement_account_id' => $this->cash->id, 'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ]]])->json('results.0');

    expect($result['status'])->toBe('accepted')->and(Transaction::count())->toBe(2);
});

test('a settlement account that may not be picked reads the same on both paths', function (string $kind) {
    $id = match ($kind) {
        'inactive' => ($this->account)('Old Cash', $this->assetSub->id, ['is_settlement' => true, 'is_active' => false])->id,
        'not a settlement account' => $this->sales->id,
        'receivable' => $this->receivable->id,
        'payable' => $this->payable->id,
    };

    refusedAlike(['template' => $this->sale->id, 'account' => $id], 'settlement_account_id', 'Please pick where the money went.');
})->with(['inactive', 'not a settlement account', 'receivable', 'payable']);

test('credit on a template that does not allow it reads the same on both paths', function () {
    refusedAlike(['template' => $this->noCredit->id, 'credit' => true], 'is_credit', 'That kind of record cannot be put on credit.');
});

test('a valid cash and a valid credit record give the same account and journal lines on both paths', function (bool $credit) {
    $lines = fn(Transaction $t) => JournalEntry::where('transaction_id', $t->id)->first()->lines->map(fn($l) => [$l->ledger_account_id, $l->debit_minor, $l->credit_minor])->all();

    $this->actingAs($this->farmerUser)->post('/my-records', ['transaction_template_id' => $this->sale->id, 'amount' => '100', 'transaction_date' => now()->toDateString()]
        + ($credit ? ['is_credit' => '1'] : ['settlement_account_id' => $this->cash->id]))->assertSessionDoesntHaveErrors();

    $this->actingAs($this->farmerUser)->postJson('/sync/submissions', ['records' => [[
        'uuid' => (string) Str::uuid(), 'template' => $this->sale->id, 'farmer' => $this->profile->uuid, 'amount' => '100',
        'event_date' => now()->toDateString(), 'device_created_at' => now()->toIso8601String(),
    ] + ($credit ? ['is_credit' => true] : ['settlement_account_id' => $this->cash->id])]])->assertOk();

    [$web, $sync] = Transaction::orderBy('id')->get()->all();

    expect($sync->settlement_account_id)->toBe($web->settlement_account_id)->and($lines($sync))->toBe($lines($web));
})->with([false, true]);
