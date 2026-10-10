<?php

use App\Models\AccountingPeriod;
use App\Models\FarmerProfile;
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

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(PermissionsSeeder::class);

    $dr = LedgerClass::create(['name' => 'Dr']);
    $cr = LedgerClass::create(['name' => 'Cr']);
    $assetSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Assets', 'class_id' => $dr->id])->id, 'name' => 'Money']);
    $incomeSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Income', 'class_id' => $cr->id])->id, 'name' => 'Farm Income']);
    $expenseSub = LedgerSubcategory::create(['category_id' => LedgerCategory::create(['name' => 'Expenses', 'class_id' => $dr->id])->id, 'name' => 'Farm Expense']);
    $control = LedgerControl::create(['name' => 'General']);
    $type = LedgerType::create(['name' => 'GL']);

    $account = fn(string $name, int $sub, bool $settlement = false) => LedgerAccount::create([
        'name' => $name, 'control_id' => $control->id, 'subcategory_id' => $sub, 'type_id' => $type->id, 'is_settlement' => $settlement,
    ]);

    $this->cash = $account('Cash', $assetSub->id, true);
    $this->receivable = $account('Accounts Receivable', $assetSub->id, true);
    $this->payable = $account('Accounts Payable', $assetSub->id, true);
    $sales = $account('Sales', $incomeSub->id);
    $feed = $account('Feed', $expenseSub->id);
    $loss = $account('Loss', $incomeSub->id);

    $this->sale = TransactionTemplate::create([
        'name' => 'I sold crops', 'slug' => 'crop_sale', 'transaction_type' => 'INCOME', 'allows_credit' => true,
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    $this->purchase = TransactionTemplate::create([
        'name' => 'I bought feed', 'slug' => 'feed', 'transaction_type' => 'EXPENSE', 'allows_credit' => true,
        'debit_account_id' => $feed->id, 'credit_account_id' => $this->cash->id, 'settlement_side' => 'credit',
    ]);
    $this->noCredit = TransactionTemplate::create([
        'name' => 'Cash only sale', 'slug' => 'cash_sale', 'transaction_type' => 'INCOME',
        'debit_account_id' => $this->cash->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'debit',
    ]);
    $this->lossTemplate = TransactionTemplate::create([
        'name' => 'An animal died', 'slug' => 'loss', 'transaction_type' => 'LOSS', 'allows_credit' => true,
        'debit_account_id' => $loss->id, 'credit_account_id' => $sales->id, 'settlement_side' => 'none',
    ]);

    AccountingPeriod::create(['name' => 'Test', 'starts_on' => now()->startOfYear()->toDateString(), 'ends_on' => now()->endOfYear()->toDateString()]);

    $this->farmerUser = User::factory()->create();
    $this->farmerUser->assignRole('farmer');
    $this->profile = FarmerProfile::factory()->create(['user_id' => $this->farmerUser->id]);
});

function creditRec(TransactionTemplate $template, array $o = []): array
{
    return array_merge([
        'uuid' => (string) Str::uuid(),
        'template' => $template->id,
        'farmer' => test()->profile->uuid,
        'amount' => '100',
        'is_credit' => true,
        'event_date' => now()->toDateString(),
        'device_created_at' => now()->toIso8601String(),
    ], $o);
}

function syncOne(array $record)
{
    return test()->actingAs(test()->farmerUser)->postJson('/sync/submissions', ['records' => [$record]])->assertOk()->json('results.0');
}

function lines(Transaction $transaction): array
{
    return JournalEntry::where('transaction_id', $transaction->id)->first()->lines
        ->map(fn($l) => [$l->ledger_account_id, $l->debit_minor, $l->credit_minor])->all();
}

test('a credit sale posts against receivable and no cash moves', function () {
    expect(syncOne(creditRec($this->sale))['status'])->toBe('accepted');

    $transaction = Transaction::first();

    expect($transaction->settlement_account_id)->toBe($this->receivable->id)
        ->and($transaction->is_credit)->toBeTrue()
        ->and(collect(lines($transaction))->pluck(0))->not->toContain($this->cash->id);
});

test('a credit purchase posts against payable', function () {
    expect(syncOne(creditRec($this->purchase))['status'])->toBe('accepted');

    expect(Transaction::first()->settlement_account_id)->toBe($this->payable->id);
});

test('credit on a template that does not allow it needs fixing with the web wording', function () {
    $result = syncOne(creditRec($this->noCredit));

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('That kind of record cannot be put on credit.')
        ->and(Transaction::count())->toBe(0);
});

test('credit on a template that is neither income nor expense is refused', function () {
    $result = syncOne(creditRec($this->lossTemplate));

    expect($result['status'])->toBe('needs_fixing')
        ->and($result['reason'])->toBe('That kind of record cannot be put on credit.')
        ->and(Transaction::count())->toBe(0);
});

test('a settlement account sent with is_credit true is ignored', function () {
    syncOne(creditRec($this->sale, ['settlement_account_id' => $this->cash->id]));

    $transaction = Transaction::first();

    expect($transaction->settlement_account_id)->toBe($this->receivable->id)
        ->and(collect(lines($transaction))->pluck(0))->not->toContain($this->cash->id);
});

test('a cash record without is_credit behaves as before', function () {
    $record = creditRec($this->noCredit, ['settlement_account_id' => $this->cash->id]);
    unset($record['is_credit']);

    expect(syncOne($record)['status'])->toBe('accepted');

    $transaction = Transaction::first();

    expect($transaction->settlement_account_id)->toBe($this->cash->id)->and($transaction->is_credit)->toBeFalse();
});

test('the web route and sync give the same settlement account and journal lines', function (string $template) {
    $template = $this->$template;

    $this->actingAs($this->farmerUser)->postJson('/my-records', [
        'transaction_template_id' => $template->id, 'amount' => '100', 'is_credit' => '1',
        'transaction_date' => now()->toDateString(), 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk();

    syncOne(creditRec($template));

    [$web, $sync] = Transaction::orderBy('id')->get()->all();

    expect($sync->settlement_account_id)->toBe($web->settlement_account_id)
        ->and(lines($sync))->toBe(lines($web));
})->with(['sale', 'purchase']);

test('a duplicate uuid of an accepted credit record posts nothing and returns the stored result', function () {
    $record = creditRec($this->sale);

    $first = syncOne($record);
    $second = syncOne($record);

    expect($second)->toBe($first)->and(Transaction::count())->toBe(1);
});
